<?php

class GT_Speed_Helper_html extends Mage_Core_Helper_Abstract {

    /**
     * Checks .htaccess status and whether GTspeed expires headers are installed
     *
     * Returns a 3 bit number that contains .htaccess status
     * 1 bit stores whether .htaccess is readable (if it isn't, the website wouldn't be accessible anyways)
     * 2 bit stores whether .htaccess is writable
     * 4 bit stores whether GTspeed expires headers are installed
     *
     * @return int
     */
    public function checkExpires() 
    {
        $htaccess = Mage::getBaseDir() . DS . ".htaccess";

        $bit = 0;

        if (!is_readable($htaccess))
            $bit = $bit | 1;

        if (!is_writable($htaccess)) {
            $bit = $bit | 2;
        }

        $content = file_get_contents($htaccess);
        if (strpos($content, "GTspeed") !== false)
            $bit = $bit | 4;

        return $bit;
    }

    /**
     * Formats expires headers setting HTML
     *
     * @return string
     */
    public function printExpiresSetting() 
    {
        $expires = $this->checkExpires();

        if ($expires & 1) {
            echo '.htaccess is <span class="red bold">not readable!</span> .htaccess needs to be readable for GTspeed to modify Expires Headers';
        } else {
            $html = '';
            if ($expires & 4) {
                $html = 'Expires Headers are <span class="green bold">enabled</span>';
            } else {
                $html = 'Expires Headers are <span class="red bold">disabled</span>';
            }

            if ($expires & 2) {
                $html .= ' [ <a href="#" title=".htaccess is <span class=\'red bold\'>not writable!</span> .htaccess needs to be writable for GTspeed to modify Expires Headers">!</a> ]';
            } else {
                $html .= ' [ <a href="'.Mage::getModel('adminhtml/url')->getUrl('gtspeed/info/expires').'">';
                $html .= ($expires & 4) ? 'disable' : 'enable';
                $html .= '</a> ]';
            }

            return $html;
        }

    }

    /**
     * Formats setting HTML for admin interface
     *
     * @param bool $setting - setting status
     * @param string $link - controller action to execute
     * @return string
     */
    public function printSetting($setting, $link) 
    {
        $enabled = '<span style="color: green;font-weight: bold;">enabled</span> [ <a href="'.Mage::getModel('adminhtml/url')->getUrl('gtspeed/info/'.$link).'">disable</a> ] ';
        $disabled = '<span style="color: red;font-weight: bold;">disabled</span> [ <a href="'.Mage::getModel('adminhtml/url')->getUrl('gtspeed/info/'.$link).'">enable</a> ]';

        return ($setting) ? $enabled : $disabled;
    }

    /**
     * Formats dependant setting HTML for admin interface
     *
     * @param bool $depend - dependant setting status
     * @param bool $setting - setting status
     * @param string $link - controller action to execute
     * @param string $msg - tooltip to display if dependant setting is disabled
     * @return string
     */
    public function printDependSetting($depend, $setting, $link, $msg = '') 
    {
        $enabled = '';
        $disabled = '';

        if ($depend) {
            $enabled = '<span style="color: green;font-weight: bold;">enabled</span> [ <a href="'.Mage::getModel('adminhtml/url')->getUrl('gtspeed/info/'.$link).'">disable</a> ] ';
            $disabled = '<span style="color: red;font-weight: bold;">disabled</span> [ <a href="'.Mage::getModel('adminhtml/url')->getUrl('gtspeed/info/'.$link).'">enable</a> ]';
        } else {
            $enabled = '<span style="color: red;font-weight: bold;">disabled</span> [ <a href="#" title="'.$msg.'">?</a> ] ';
            $disabled = '<span style="color: red;font-weight: bold;">disabled</span> [ <a href="#" title="'.$msg.'">?</a> ]';
        }

        return ($setting) ? $enabled : $disabled;
    }

    /**
     * Formats scheduled settings HTML form
     *
     * @return string
     */
    public function printScheduleForm() 
    {

        $frequencyDaily     = Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_DAILY;
        $frequencyWeekly    = Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_WEEKLY;
        $frequencyMonthly   = Mage_Adminhtml_Model_System_Config_Source_Cron_Frequency::CRON_MONTHLY;

        $cron_enabled = Mage::getStoreConfigFlag('gtspeed/cron/enabled');
        $freq = Mage::getStoreConfig('gtspeed/cron/frequency');
        switch ($freq) {
        case 'D':
            $freq = "Daily";
            break;
        case 'W':
            $freq = "Weekly";
            break;
        case 'M':
            $freq = "Monthly";
            break;
        }

        $freqs = array(
            array(
                'label' => 'Daily',
                'value' => $frequencyDaily,
            ),
            array(
                'label' => 'Weekly',
                'value' => $frequencyWeekly,
            ),
            array(
                'label' => 'Monthly',
                'value' => $frequencyMonthly,
            ),
        );

        $time = Mage::getStoreConfig('gtspeed/cron/time');
        $time = explode(",", $time);

        $html = '';

        $html .= '<p><label><input type="checkbox" name="cron_enabled"'. ( ($cron_enabled) ? 'checked' : '' ) .'/>Enable Automatic Scan and Optimization of Images</label></p>';
        $html .= '<div>';
        $html .= '<select name="frequency"'.((!$cron_enabled) ? ' disabled="disabled"' : '').'style="margin-right: 5px;">';

        foreach ($freqs as $item) {
            $html .= '<option value="'.$item['value'].'"'. (($freq == $item['label']) ? ' selected="selected"' : '' ).'>'.$item['label'].'</option>';
        }

        $html .= '</select>';
        $html .= '<label>at</label>';
        $html .= '<select name="time[]" class="time"'.((!$cron_enabled) ? ' disabled="disabled"' : '').'>';

        for( $i=0;$i<24;$i++ ) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            $html .= '<option value="'.$hour.'" '. ( ($time[0] == $hour) ? 'selected="selected"' : '' ) .'>' . $hour . '</option>';
        }

        $html .= '</select>';
        $html .= '<label>:</label>';
        $html .= '<select name="time[]" class="time"'.((!$cron_enabled) ? ' disabled="disabled"' : '').'>';

        for( $i=0;$i<60;$i++ ) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            $html.= '<option value="'.$hour.'" '. ( ($time[1] == $hour) ? 'selected="selected"' : '' ) .'>' . $hour . '</option>';
        }

        $html .= '</select>';
        $html .= '<label>:</label>';
        $html .= '<select name="time[]" class="time"'.((!$cron_enabled) ? ' disabled="disabled"' : '').'>';

        for( $i=0;$i<60;$i++ ) {

            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            $html .= '<option value="'.$hour.'" '. ( ($time[2] == $hour) ? 'selected="selected"' : '' ) .'>' . $hour . '</option>';
        }

        $html .= '</select>';
        $html .= '<button class="scalable" type="submit" name="submit" value="submit"><span>Save</span></button>';
        $html .= '</div>';
        return $html;
    }

    /**
     * Formats Total Images HTML
     *
     * @return string
     */
    public function getTotalImages() 
    {
        $collection = Mage::getModel('gtspeed/image')->getCollection();
        $count = $collection->getSize();

        $html = '';

        if (!$count) {
            $html = '<span class="italic grey">Please Update the Image Library.</span>';
        } else {
            $html = '<span class="orange bold">'.$count.'</span>';
        }

        return $html;
    }

    /**
     * Formats Unoptimized Images HTML
     *
     * @return string
     */
    public function getUnoptimizedImages() 
    {
        $library = Mage::getModel('gtspeed/image')->getCollection();
        $lib_count = $library->getSize();

        $collection = Mage::getModel('gtspeed/image')->getCollection()->addOptimizedFilter(0);
        $count = $collection->getSize();

        $html = '';

        if (!$lib_count) {
            $html = '-';
        } else {
            if ($count) 
                $html = '<span class="ltred bold">'.$count.'</span>';
            else
                $html = '<span class="ltgreen bold">'.$count.'</span>';
        }

        return $html;
    }

    /**
     * Formats Space Savings HTML
     *
     * @return string
     */
    public function getSpaceSavings() 
    {
        $library = Mage::getModel('gtspeed/image')->getCollection();
        $lib_count = $library->getSize();

        $stat = Mage::getModel('gtspeed/stat')->loadByName('image_opt');
        $html = '';
        if (!$stat OR !$lib_count) {
            $html = '-';
        } else {
            $save = $stat->getStat();

            if ($save)
                $html = '<span class="ltgreen bold">'.$save.'kb</span>';
            else
                $html = '<span class="bold">0</span>';
        }

        return $html;
    }
}
