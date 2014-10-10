<?php

class GT_Speed_Model_System_Config_Source_Expires_Filetype
{

    protected static $_options;

    const TYPE_CSS    = 'css';
    const TYPE_JS   = 'js';
    const TYPE_JPG   = 'jpg';
    const TYPE_PNG   = 'png';
    const TYPE_GIF   = 'gif';

    public function toOptionArray()
    {
        if (!self::$_options) {
            self::$_options = array(
                array(
                    'label' => Mage::helper('gtspeed')->__('CSS'),
                    'value' => self::TYPE_CSS,
                ),
                array(
                    'label' => Mage::helper('gtspeed')->__('JavaScript'),
                    'value' => self::TYPE_JS,
                ),
                array(
                    'label' => Mage::helper('gtspeed')->__('JPEG'),
                    'value' => self::TYPE_JPG,
                ),
                array(
                    'label' => Mage::helper('gtspeed')->__('PNG'),
                    'value' => self::TYPE_PNG,
                ),
                array(
                    'label' => Mage::helper('gtspeed')->__('GIF'),
                    'value' => self::TYPE_GIF,
                ),
            );
        }
        return self::$_options;
    }

}

