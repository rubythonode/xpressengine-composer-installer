<?php
/**
 * This file is XpressEngine 3rd party plugin installer.
 *
 * PHP version 5
 *
 * @category    Installer
 * @package     Xpressengine\Installer
 * @author      XE Team (jhyeon1010) <cjh1010@xpressengine.com>
 * @copyright   2014 Copyright (C) NAVER <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/lgpl-3.0-standalone.html LGPL
 * @link        http://www.xpressengine.com
 */
namespace Xpressengine\Installer;

use Composer\Composer;
use Composer\Downloader\TransportException;
use Composer\Installer\BinaryInstaller;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;

/**
 * This class is extend composer installer for XpressEngine plugins.
 *
 * @category    Installer
 * @package     Xpressengine\Installer
 * @author      XE Team (jhyeon1010) <cjh1010@xpressengine.com>
 * @copyright   2014 Copyright (C) NAVER <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/lgpl-3.0-standalone.html LGPL
 * @link        http://www.xpressengine.com
 */
class XpressengineInstaller extends LibraryInstaller
{

    /**
     * @var array
     */
    public static $changed = [];

    public static $failed = [];


    /**
     * Initializes library installer.
     *
     * @param IOInterface $io
     * @param Composer $composer
     * @param string $type
     * @param Filesystem $filesystem
     * @param BinaryInstaller $binaryInstaller
     */
    public function __construct(IOInterface $io, Composer $composer, $type = 'library', Filesystem $filesystem = null, BinaryInstaller $binaryInstaller = null)
    {
        static::$changed = [
            'installed' => [],
            'updated' => [],
            'uninstalled' => [],
        ];

        static::$failed = [
            'install' => [],
            'update' => [],
        ];

        parent::__construct($io, $composer, $type, $filesystem, $binaryInstaller);
    }

    /**
     * Directory in which the plugin is installed
     *
     * @param PackageInterface $package 3rd party plugin package instance
     * @return string
     */
    public function getInstallPath(PackageInterface $package)
    {
        list(, $packageName) = explode('/', $package->getPrettyName());

        return 'plugins/' . $packageName;
    }

    /**
     * Decides if the installer supports the given type
     *
     * Check XpressEngine plugin type
     *
     * @param string $packageType type of package
     * @return bool
     */
    public function supports($packageType)
    {
        return 'xpressengine-plugin' === $packageType;
    }

    /**
     * {@inheritDoc}
     */
    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        return parent::isInstalled($repo, $package);
    }

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $this->io->write("xpressengine-installer: installing ".$package->getName());

        if (!defined('__XE_PLUGIN_MODE__')) {
            $this->io->write("xpressengine-installer: skip to install ".$package->getName());
        } elseif ($this->checkDevPlugin($package)) {
            $this->io->write("xpressengine-installer: skip to install ".$package->getName());
        } else {
            try {
                parent::install($repo, $package);
            } catch(TransportException $e) {
                static::$failed['install'][$package->getName()] = $e->getStatusCode();
                throw $e;
            }
            static::$changed['installed'][$package->getName()] = $package->getPrettyVersion();
        }
    }

    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {

        $this->io->write("xpressengine-installer: updating ".$target->getName());

        if (!defined('__XE_PLUGIN_MODE__')) {
            $this->io->write("xpressengine-installer: skip to update ".$initial->getName());
        } elseif ($this->checkDevPlugin($initial)) {
            $this->io->write("xpressengine-installer: skip to update ".$initial->getName());
        } else {
            try {
                parent::update($repo, $initial, $target);
            } catch(TransportException $e) {
                static::$failed['update'][$target->getName()] = $e->getStatusCode();
                throw $e;
            }
            static::$changed['updated'][$target->getName()] = $target->getPrettyVersion();
        }
    }

    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {

        $this->io->write("xpressengine-installer: uninstalling ".$package->getName());
        $extra = $this->composer->getPackage()->getExtra();
        $path = $extra['xpressengine-plugin']['path'];
        $data = json_decode(file_get_contents($path), true);

        if(!defined('__XE_PLUGIN_MODE__')) {
            $this->io->write("xpressengine-installer: skip to uninstall ".$package->getName());
            return;
        }

        // uninstall에 등록된 플러그인이 아닐 경우 skip
        if (isset($data['xpressengine-plugin']['operation']['uninstall']) && in_array(
                $package->getName(),
                $data['xpressengine-plugin']['operation']['uninstall']
            )
        ) {
            parent::uninstall($repo, $package);
            static::$changed['uninstalled'][$package->getName()] = $package->getPrettyVersion();
        } else {
            $this->io->write("xpressengine-installer: skip to uninstall ".$package->getName());
            if($this->checkDevPlugin($package)) {
                $repo->removePackage($package);
            }
        }
    }

    protected function checkDevPlugin(PackageInterface $package)
    {
        $path = $this->getInstallPath($package);

        if(file_exists($path.'/vendor')) {
            return true;
        }
        return false;
    }
}
