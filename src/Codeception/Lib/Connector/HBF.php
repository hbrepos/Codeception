<?php
namespace Codeception\Lib\Connector;

use function Codeception\Extension\codecept_log;
use Codeception\Lib\Connector\HBF\RedirectException;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\BrowserKit\Response;
use Codeception\Subscriber\ErrorHandler;
use Symfony\Component\BrowserKit\Request as BrowserKitRequest;

class HBF extends Client {


    use Shared\PhpSuperGlobalsConverter;

    /**
     * What sort of request we're doing - sets HostBill \FrontController accordingly
     * @var string
     */
    private $engine_type = 'user';

    /**
     * Setter for $engine_type
     * @see $engine_type
     * @param $type
     */
    public function setEngineType($type) {
        $this->engine_type =  $type;
    }


    /**
     * @var bool
     */
    private $firstRequest = true;

    /**
     * Initialize the HostBill framework.
     * Consider storing db reference here.
     *
     */
    private function initialize()
    {
        include $this->module->config['bootstrap_file'];
        $this->revertErrorHandler();

    }

    /**
     * Revert back to the Codeception error handler,
     * because HostBill registers it's own error handler.
     */
    protected function revertErrorHandler()
    {
        $handler = new ErrorHandler();
        set_error_handler([$handler, 'errorHandler']);
    }

    /**
     * @var \Codeception\Module\HBF
     */
    public $module;



    /**
     *
     * @param \Symfony\Component\BrowserKit\Request $request
     *
     * @return \Symfony\Component\BrowserKit\Response
     */
    public function doRequest($request)
    {

        if ($this->firstRequest) {
            $this->initialize();
        }
        $this->firstRequest = false;

        //Set event handler that should catch redirects, so we can get proper http code

        \HBEventManager::addProceduralObserver('before_redirect',function(&$href) {
            throw new RedirectException($href,302);
        });

        $http_code = 200;

        $this->headers = [];
        $_COOKIE        = array_merge($_COOKIE, $request->getCookies());
        $_SERVER        = array_merge($_SERVER, $request->getServer());
        $_FILES         = $this->remapFiles($request->getFiles());
        $_REQUEST       = $this->remapRequestParameters($request->getParameters());
        $_POST          = $_GET = [];

        if (strtoupper($request->getMethod()) == 'GET') {
            $_GET = $_REQUEST;
        } else {
            $_POST = $_REQUEST;
        }

        // Parse url parts
        $uriPath = ltrim(parse_url($request->getUri(), PHP_URL_PATH), '/');
        $uriQuery = ltrim(parse_url($request->getUri(), PHP_URL_QUERY), '?');
        $scriptName = 'index.php';
        if (!empty($uriQuery)) {
            $uriPath .= "?{$uriQuery}";

            parse_str($uriQuery, $params);
            foreach ($params as $k => $v) {
                $_GET[$k] = $v;
            }
        }

        // Add script name to request if none
        if ($scriptName and strpos($uriPath, $scriptName) === false) {
            $uriPath = "/{$scriptName}/{$uriPath}";
        }

        // Add forward slash if not exists
        if (strpos($uriPath, '/') !== 0) {
            $uriPath = "/{$uriPath}";
        }

        $_SERVER['REQUEST_METHOD'] = strtoupper($request->getMethod());
        $_SERVER['REQUEST_URI'] = $uriPath;


        ob_start();

        try  {
            \FrontController::init($this->engine_type);
        } catch (RedirectException $e) {
            $http_code = 302;
        }


        $content = ob_get_clean();
        ob_end_clean();




        $headers = [];
        $php_headers = headers_list();
        foreach ($php_headers as $value) {
            // Get the header name
            $parts = explode(':', $value);
            if (count($parts) > 1) {
                $name = trim(array_shift($parts));
                // Build the header hash map
                $headers[$name] = trim(implode(':', $parts));
            }
        }
        $headers['Content-type'] = isset($headers['Content-type'])
            ? $headers['Content-type']
            : "text/html; charset=UTF-8";


        $response = new Response($content, $http_code, $headers);
        return $response;


    }

}