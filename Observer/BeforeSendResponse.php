<?php
namespace Sga\DeferJs\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Sga\DeferJs\Helper\Config as ConfigHelper;

class BeforeSendResponse implements ObserverInterface
{
    protected $_configHelper;
    protected $_appState;
    protected $_scopeConfig;

    const EXCLUDE_FLAG = 'data-defer="false"';

    public function __construct(
        ConfigHelper $configHelper,
        AppState $appState,
        ScopeConfigInterface $scopeConfig
    ){
        $this->_configHelper = $configHelper;
        $this->_appState = $appState;
        $this->_scopeConfig = $scopeConfig;
    }

    public function execute(Observer $observer)
    {
        if ($this->_appState->getAreaCode() === 'frontend' && $this->_configHelper->isEnabled() && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $response = $observer->getEvent()->getData('response');
            if ($response) {
                $html = $response->getBody();

                $conditionalJsPattern = '@(?:<script(.*)>)(.*)</script>@msU';
                preg_match_all($conditionalJsPattern, $html, $matches);

                $patterns = $this->_getPatterns();
                foreach ($matches[0] as $i => $js) {
                    if (strpos($js, self::EXCLUDE_FLAG) !== false) {
                        continue;
                    }

                    $jsDefer = '<script defer="defer" '.$matches[1][$i].'>';

                    $foundPattern = false;
                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $matches[2][$i])) {
                            $foundPattern = true;
                            $jsDefer .= 'document.addEventListener("DOMContentLoaded", function() {'.$matches[2][$i].'});';
                            break;
                        }
                    }

                    if (!$foundPattern) {
                        $jsDefer .= $matches[2][$i];
                    }

                    $jsDefer .= '</script>';

                    $html = str_replace($js, $jsDefer, $html);
                }

                $response->setBody($html);
            }
        }
    }

    protected function _getPatterns()
    {
        $list = [];
        $nodes = $this->_scopeConfig->get('system', 'default/dev/js/patterns');
        if (is_array($nodes)) {
            foreach ($nodes as $key => $node) {
                $list[] = $node;
            }
        }
        return $list;
    }
}
