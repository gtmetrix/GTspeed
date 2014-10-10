<?php

class GT_Speed_Model_System_Config_Backend_Expires extends Mage_Core_Model_Config_Data
{
    const EXPIRES_ENABLED_PATH  = 'gtspeed/expires/enabled';
    const EXPIRES_FILETYPES_PATH  = 'gtspeed/expires/filetypes';
    const EXPIRES_TIME_PATH  = 'gtspeed/expires/time';

    /**
     * _afterLoad() for gtspeed/expires/enabled
     *
     * Checks whether GTspeed expires headers is installed and corrects the setting if it is wrong
     * This only runs in System > Configuration
     *
     * @return this
     */
    protected function _afterLoad()
    {
        $enabled = Mage::getStoreConfig(self::EXPIRES_ENABLED_PATH);

        $htaccess = Mage::getBaseDir() . DS . ".htaccess";
        $readable = is_readable($htaccess);
        $writable = is_writable($htaccess);
        $installed = false;

        if (!$readable) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('.htaccess is not readable! .htaccess must be readable to modify Expires Headers.'));
            return $this;
        }

        $content = file_get_contents($htaccess);

        if (strpos($content, "GTspeed") !== false) {
            $installed = true;
        }

        if ($enabled && !$installed) {
            try {
                Mage::getModel('core/config_data')
                    ->load(self::EXPIRES_ENABLED_PATH, 'path')
                    ->setValue(0)
                    ->setPath(self::EXPIRES_ENABLED_PATH)
                    ->save();
                // clear config cache to refresh settings
                Mage::getConfig()->cleanCache();
            } catch (Exception $e) {
                throw new Exception(Mage::helper('gtspeed')->__('Unable to save setting.'));
            }
        } else if (!$enabled && $installed) {
            try {
                Mage::getModel('core/config_data')
                    ->load(self::EXPIRES_ENABLED_PATH, 'path')
                    ->setValue(1)
                    ->setPath(self::EXPIRES_ENABLED_PATH)
                    ->save();
                // clear config cache to refresh settings
                Mage::getConfig()->cleanCache();
            } catch (Exception $e) {
                throw new Exception(Mage::helper('gtspeed')->__('Unable to save setting.'));
            }
        }

        return $this;
    }

    /**
     * _afterSave() for gtspeed/expires/enabled
     *
     * Installs GTspeed expires headers in .htaccess
     */
    protected function _afterSave()
    {
        $enabled     = $this->getData('groups/expires/fields/enabled/value');
        $filetypes    = $this->getData('groups/expires/fields/filetypes/value');
        $time        = $this->getData('groups/expires/fields/time/value');

        // if $enabled is null, we are setting this from the admin interface so just get the value for $enabled
        // and load store config values for the other settings
        if (is_null($enabled)) {
            $enabled = $this->getValue();
        }
        if (is_null($filetypes)) {
            $filetypes = Mage::getStoreConfig(self::EXPIRES_FILETYPES_PATH);
            $filetypes = explode(',', $filetypes);
        }
        if (is_null($time)) {
            $time = Mage::getStoreConfig(self::EXPIRES_TIME_PATH);
        }

        $htaccess = Mage::getBaseDir() . DS . ".htaccess";
        $readable = is_readable($htaccess);
        $writable = is_writable($htaccess);
        $installed = false;

        if (!$readable) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('.htaccess is not readable! .htaccess must be readable to modify Expires Headers.'));
            return;
        }

        $content = file_get_contents($htaccess);

        // check for GTspeed expires headers install
        if (strpos($content, "GTspeed") !== false) {
            $installed = true;
        }

        if (!$writable) {
            if ($enabled && !$installed) {
                //no write access, disable the option and send an error
                try {
                    Mage::getModel('core/config_data')
                        ->load(self::EXPIRES_ENABLED_PATH, 'path')
                        ->setValue(0)
                        ->setPath(self::EXPIRES_ENABLED_PATH)
                        ->save();
                    // clear config cache to refresh settings
                    Mage::getConfig()->cleanCache();
                    Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('.htaccess is not writable! .htaccess must be writable to modify Expires Headers.'));
                } catch (Exception $e) {
                    throw new Exception(Mage::helper('gtspeed')->__('Unable to save setting.'));
                }
            } else if (!$enabled && $installed) {
                //no write access, preserve the option and send an error
                try {
                    Mage::getModel('core/config_data')
                        ->load(self::EXPIRES_ENABLED_PATH, 'path')
                        ->setValue(1)
                        ->setPath(self::EXPIRES_ENABLED_PATH)
                        ->save();
                    // clear config cache to refresh settings
                    Mage::getConfig()->cleanCache();
                    Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('.htaccess is not writable! .htaccess must be writable to modify Expires Headers.'));
                } catch (Exception $e) {
                    throw new Exception(Mage::helper('gtspeed')->__('Unable to save setting.'));
                }

            }
        } else {
            if ($enabled) {
                // if installed, get rid of the old block and replace it in case any settings changed
                if ($installed) {
                    $content = preg_replace('/(.*)(#GTspeed.*#GTspeed)(.*)/is', '\1\3', $content);
                }

                // add expires headers
                $expires = '';

                // process regex and mimetypes
                $regex = array();
                $mime = array();
                foreach ($filetypes as $type) {
                    switch($type) {
                    case GT_Speed_Model_System_Config_Source_Expires_Filetype::TYPE_CSS:
                        $regex[] = 'css';
                        $mime[] = 'text/css';
                        break;
                    case GT_Speed_Model_System_Config_Source_Expires_Filetype::TYPE_JS:
                        $regex[] = 'js';
                        $mime[] = 'text/javascript';
                        $mime[] = 'application/x-javascript';
                        break;
                    case GT_Speed_Model_System_Config_Source_Expires_Filetype::TYPE_JPG:
                        $regex[] = 'jpe?g';
                        $mime[] = 'image/jpeg';
                        break;
                    case GT_Speed_Model_System_Config_Source_Expires_Filetype::TYPE_PNG:
                        $regex[] = 'png';
                        $mime[] = 'image/png';
                        break;
                    case GT_Speed_Model_System_Config_Source_Expires_Filetype::TYPE_GIF:
                        $regex[] = 'gif';
                        $mime[] = 'image/gif';
                        break;
                    }

                }

                // format expires headers
                $expires .= '#GTspeed'."\n";
                $expires .= '<IfModule mod_headers.c>'."\n";
                $expires .= '<FilesMatch "\.('.implode('|', $regex).')$">'."\n";
                $expires .= 'Header set Cache-Control "max-age='.$time.', public"'."\n";
                $expires .= '</FilesMatch>'."\n";
                $expires .= '</IfModule>'."\n";
                $expires .= '<IfModule mod_expires.c>'."\n";
                $expires .= 'ExpiresActive On'."\n";

                foreach ($mime as $m) {
                    $expires .= 'ExpiresByType '.$m.' M'.$time."\n";
                }

                $expires .= '</IfModule>'."\n";
                $expires .= '#GTspeed'."\n";

                // write to .htaccess
                $content .= $expires;
                if (file_put_contents($htaccess, $content) === false) {
                    try {
                        Mage::getModel('core/config_data')
                            ->load(self::EXPIRES_ENABLED_PATH, 'path')
                            ->setValue(0)
                            ->setPath(self::EXPIRES_ENABLED_PATH)
                            ->save();
                        Mage::getConfig()->cleanCache();
                        Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Failed writing to .htaccess'));
                    } catch (Exception $e) {
                        throw new Exception(Mage::helper('gtspeed')->__('Unable to save setting.'));
                    }
                } else {
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('Successfully written to .htaccess!'));
                }

            } else if (!$enabled && $installed) {
                //remove expires headers

                $content = preg_replace('/(.*)(#GTspeed.*#GTspeed)(.*)/is', '\1\3', $content);
                if (file_put_contents($htaccess, $content) === false) {
                    Mage::getModel('core/config_data')
                        ->load(self::EXPIRES_ENABLED_PATH, 'path')
                        ->setValue(1)
                        ->setPath(self::EXPIRES_ENABLED_PATH)
                        ->save();
                    Mage::getConfig()->cleanCache();
                    Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Failed writing to .htaccess'));
                } else {
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('Successfully written to .htaccess!'));
                }
            }
        }

    }
}

