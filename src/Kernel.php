<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    public const TIMEZONE = 'Europe/Sofia';

    public function boot(): void
    {
        // The hosting's php.ini timezone is not under our control; the site lives in Sofia time.
        date_default_timezone_set(self::TIMEZONE);
        parent::boot();
    }

    /**
     * The web root lives in two different places:
     *  - locally / in CI:  <project>/public_html
     *  - on SuperHosting:  /home/zaniskyu/public_html, next to the project in /home/zaniskyu/zanimalnik
     *
     * The project folder on the server never contains public_html (the deploy uploads it
     * straight into the locked web root), so its absence tells us we are on the server.
     */
    public function getPublicDir(): string
    {
        $inside = $this->getProjectDir().'/public_html';

        return is_dir($inside) ? $inside : \dirname($this->getProjectDir()).'/public_html';
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->setParameter('app.public_dir', $this->getPublicDir());
    }

    /**
     * FrameworkBundle derives the public dir from composer.json (project_dir + extra.public-dir),
     * which is wrong on the server. Point AssetMapper at the real web root.
     */
    public function process(ContainerBuilder $container): void
    {
        $publicDir = $this->getPublicDir();

        if ($container->hasDefinition('asset_mapper.local_public_assets_filesystem')) {
            $container->getDefinition('asset_mapper.local_public_assets_filesystem')
                ->setArgument(0, $publicDir);
        }

        if ($container->hasDefinition('asset_mapper.compiled_asset_mapper_config_reader')) {
            // the configured framework.asset_mapper.public_prefix (default "/assets/")
            $prefix = trim((string) $container->getDefinition('asset_mapper.public_assets_path_resolver')->getArgument(0), '/');
            $container->getDefinition('asset_mapper.compiled_asset_mapper_config_reader')
                ->setArgument(0, $publicDir.'/'.$prefix);
        }
    }
}
