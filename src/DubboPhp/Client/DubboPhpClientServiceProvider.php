<?php
/**
 * Created by IntelliJ IDEA.
 * User: user
 * Date: 2017/3/8
 * Time: 17:06
 */

namespace DubboPhp\Client;

use Illuminate\Support\ServiceProvider;

use Illuminate\Foundation\Application as LaravelApplication;
use Laravel\Lumen\Application as LumenApplication;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class DubboPhpClientServiceProvider extends ServiceProvider
{

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
        $source = dirname(dirname(__DIR__)).'/config/config.php';

        if ($this->app instanceof LaravelApplication && $this->app->runningInConsole()) {
            $this->publishes([$source => config_path('dubbo_cli.php')]);
        } elseif ($this->app instanceof LumenApplication) {
            $this->app->configure('dubbo_cli');
        }

        $this->mergeConfigFrom($source, 'dubbo_cli');
    }

    protected function getLoggerByParams($parameters=[]){
        $logger = null;
        if(isset($parameters['logger']) && !empty($parameters['logger'])){
            if($parameters['logger']===true){
                if(isset($parameters['log_file']) && !empty($parameters['log_file'])){
                    $logger = new Logger('dubbo_rpc');
                    $logger->pushHandler(new StreamHandler($parameters['log_file'], Logger::DEBUG));
                }else{
                    $logger = $this->app['log']->getMonolog();
                }
            }elseif(is_string($parameters) && class_exists($parameters)){
                $logger = $this->app->make($parameters);
            }
        }
        return $logger;
    }
    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->app->bind('dubbo_cli.factory', function ($app,$parameters=[]) {
            $parameters+=$app['config']->get('dubbo_cli.default',[]);
            return new Client($parameters,$this->getLoggerByParams($parameters));
        });
        $this->app->singleton('dubbo_cli',function($app){
            $parameters = $app['config']->get('dubbo_cli.default',[]);
            return new Client($parameters,$this->getLoggerByParams($parameters));
        });
        $this->app->alias('dubbo_cli.factory', 'DubboPhp\Client\Client');
        $this->app->alias('dubbo_cli', 'DubboPhp\Client\Client');
//        $this->app->alias('DubboPhpClient', 'DubboPhp\Client\Facades\DubboPhpClient');
//        $this->app->alias('DubboPhpClientFactory', 'DubboPhp\Client\Facades\DubboPhpClientFactory');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'dubbo_cli', 'dubbo_cli.factory',
//            'DubboPhpClient','DubboPhpClientFactory'
        ];
    }

}