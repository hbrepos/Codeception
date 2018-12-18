<?php
namespace Codeception\Module;

use Codeception\Lib\Framework;
use Codeception\TestInterface;
use Codeception\Configuration;
use Codeception\Lib\ModuleContainer;
use Codeception\Util\ReflectionHelper;
use Codeception\Lib\Connector\HBF as HBFConnector;

/**
 * This module allows you to run tests inside HostBill Framework
 *
 * File `init_autoloader` in project's root is required by Zend Framework 2.
 * Uses `tests/application.config.php` config file by default.
 *
 * Note: services part and Doctrine integration is not compatible with ZF3 yet
 *
 * ## Status
 *
 * * Maintainer: **Naktibalda**
 * * Stability: **stable**
 *
 * ## Config
 *
 * * config: relative path to config file (default: `tests/application.config.php`)
 *
 * ## Public Properties
 *
 * * application -  instance of `\Zend\Mvc\ApplicationInterface`
 * * db - instance of `\Zend\Db\Adapter\AdapterInterface`
 * * client - BrowserKit client
 *
 * ## Parts
 *
 * * services - allows to use grabServiceFromContainer and addServiceToContainer with WebDriver or PhpBrowser modules.
 *
 * Usage example:
 *
 * ```yaml
 * actor: AcceptanceTester
 * modules:
 *     enabled:
 *         - HBF
 * ```
 */
class HBF extends Framework {

    /**
     * @var \Codeception\Lib\Connector\HBF
     */
    public $client;



    /**
     * @var array
     */
    public $config = [];



    /**
     * Constructor.
     *
     * @param ModuleContainer $container
     * @param array|null $config
     */
    public function __construct(ModuleContainer $container, $config = null)
    {
        $this->config = array_merge(
            [
                'bootstrap' => 'hbf' . DIRECTORY_SEPARATOR . 'bootstrap.php',
                'root' => '',

            ],
            (array)$config
        );

        $projectDir = \Codeception\Configuration::projectDir();
        $projectDir .= $this->config['root'];

        $this->config['project_dir'] = $projectDir;
        $this->config['bootstrap_file'] = $projectDir . $this->config['bootstrap'];

        parent::__construct($container);
    }

    /*
    * Create the client connector. Called before each test
    */
    public function _createClient()
    {
        $this->client = new HBFConnector();
        $this->client->module = $this;

    }

    public function _initialize()
    {
        $this->_createClient();

    }

    public function _before(TestInterface $test)
    {
    }

    public function _after(TestInterface $test)
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];



        parent::_after($test);
    }

    public function _afterSuite()
    {
        unset($this->client);
    }


    /**
     * Request should be in admin mode.
     * Use this call before any other calls in test
     */
    public function amAdmin() {
        $this->client->setEngineType('admin');
    }

    /**
     * Request should be in user mode.
     * This is default type of request.
     */
    public function amUser() {
        $this->client->setEngineType('user');
    }

    /**
     * Request should be in cli mode.
     * Use this call before any other calls in test
     */
    public function amCLI() {
        $this->client->setEngineType('cli');
    }



}