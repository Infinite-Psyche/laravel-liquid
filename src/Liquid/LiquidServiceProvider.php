<?php
/**
 * Created by PhpStorm.
 * User: joro
 * Date: 14.2.2019 г.
 * Time: 08:29 ч.
 */

namespace Liquid;

use Illuminate\View\FileViewFinder;
use Illuminate\View\ViewServiceProvider;

class LiquidServiceProvider extends ViewServiceProvider
{

    public function register()
    {
        parent::register();

        $this->mergeConfigFrom($file = __DIR__ . '/../../config/liquid.php', 'liquid');

        $this->publishes([
            $file => config_path('liquid.php')
        ], 'config');

        $this->registerLiquidEngine($this->app['view.engine.resolver']);
    }

    public function boot()
    {
        $this->app['view']->addExtension($this->app['config']->get('liquid.extension'), 'liquid');
    }

    /**
     * Register the view finder implementation.
     *
     * @return void
     */
    public function registerViewFinder()
    {
        $this->app->bind('view.finder', function ($app) {
            $finder = new FileViewFinder($app['files'], $app['config']['view.paths']);
            $finder->addExtension($this->app['config']->get('liquid.extension'));
            return $finder;
        });
    }

    /**
     * Register the Blade engine implementation.
     *
     * @param  \Illuminate\View\Engines\EngineResolver  $resolver
     * @return void
     */
    public function registerLiquidEngine($resolver)
    {
        // The Compiler engine requires an instance of the CompilerInterface, which in
        // this case will be the Blade compiler, so we'll first create the compiler
        // instance to pass into the engine so it can compile the views properly.
        $this->app->singleton('liquid.compiler', function () {
            return new LiquidCompiler(
                $this->app['files'], $this->app['config']['view.compiled']
            );
        });

        $resolver->register('liquid', function () {
            return new CompilerEngine($this->app['liquid.compiler'], $this->app['config']->get('liquid', []));
        });
    }

}