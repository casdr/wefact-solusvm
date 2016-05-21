<?php

namespace modules\products\vps\integrations\SolusVM;

/**
 * -------------------------------------------------------------------------------------
 * SolusVM.
 *
 * Author       : Cas de Reuver
 * Copyright	: 2016
 * Version 		: v1.0
 *
 * CHANGE LOG:
 * -------------------------------------------------------------------------------------
 *  2016-05-21		Cas de Reuver 		Initial version
 * -------------------------------------------------------------------------------------
 */
class SolusVM
{
    // use these to connect to VPS platform
    public $ServerURL, $ServerUser, $ServerPass, $NodeGroup;

    public $Error;
    public $Warning;
    public $Success;

    public function __construct()
    {
        $this->Error = [];
        $this->Warning = [];
        $this->Success = [];

        $this->loadLanguageArray(LANGUAGE_CODE);
    }

    public function doCall($postfields)
    {
        $postfields['id'] = $this->ServerUser;
        $postfields['key'] = $this->ServerPass;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->ServerURL.'/command.php');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Expect:']);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        $data = curl_exec($ch);
        file_put_contents(__DIR__.'/bla.json', file_get_contents(__DIR__.'/bla.json')."\n[$this->ServerURL] ".$data);
        curl_close($ch);
      // Parse the returned data and build an array
      preg_match_all('/<(.*?)>([^<]+)<\/\\1>/i', $data, $match);
        if (isset($postfields['rdtype'])) {
            return $data;
        }
        $result = [];
        foreach ($match[1] as $x => $y) {
            $result[$y] = $match[2][$x];
        }

        return $result;
    }

    /**
     * Use this function to prefix all errors messages with your VPS platform.
     *
     * @param string $message The error message
     *
     * @return bool Always false
     */
    private function __parseError($message)
    {
        $this->Error[] = 'SolusVM: '.$message;

        return false;
    }

    /**
     * Get list of templates from the VPS platform.
     *
     * @return array List of templates
     */
    public function getTemplateList()
    {
        $response = json_decode($this->doCall(['action' => 'list-plans', 'type' => 'kvm', 'rdtype' => 'json']), true);
        if ($response) {
            if (count($response['plans']) == 0) {
                return $this->__parseError(__('node has no templates', 'SolusVM'));
            }

            $templates = [];
            $i = 0;
            foreach ($response['plans'] as $template) {
                $templates[$i]['templateid'] = $template['id'];
                $templates[$i]['templatename'] = $template['name'];
                $templates[$i]['memory'] = $template['ram'] / 1024 / 1024;
                $templates[$i]['diskspace'] = $template['disk'] / 1024 / 1024 / 1024;
                $templates[$i]['cpucores'] = $template['cpus'];
                $templates[$i]['bandwidth'] = $template['bandwidth'] / 1024 / 1024 / 1024;
                $i++;
            }

            return $templates;
        }
        $this->__parseError($response['statusmsg']);

        return false;
    }

    /**
     * Perform an action on the VPS (eg pause, start, restart).
     *
     * @param string $vps_id ID of the VPS on the VPS platform
     * @param string $action Type of action
     *
     * @return bool True on success; False otherwise.
     */
    public function doServerAction($vps_id, $action)
    {
        switch ($action) {
            case 'pause':
                $response = $this->doCall([
                    'action'    => 'vserver-shutdown',
                    'vserverid' => $vps_id,
                    ]);
            break;

            case 'start':
                $response = $this->doCall([
                    'action'    => 'vserver-boot',
                    'vserverid' => $vps_id,
                    ]);
            break;

            case 'restart':
                $response = $this->doCall([
                    'action'    => 'vserver-reboot',
                    'vserverid' => $vps_id,
                    ]);
            break;
        }

        /*
    	 * Step 2) provide feedback to WeFact
    	 */

        if ($response && $response['status'] == 'success') {
            return true;
        } else {
            $this->__parseError($response['statusmsg']);

            return false;
        }
    }

    /**
     * Get template details from VPS platform.
     *
     * @param string $template_id ID of the template on the VPS platform
     *
     * @return array Array with template details
     */
    public function getTemplate($template_id)
    {
        /*
    	 * Step 1) get template
         */
      $response = json_decode($this->doCall(['action' => 'list-plans', 'type' => 'kvm', 'rdtype' => 'json']), true);
        /*
    	 * Step 2) provide feedback to WeFact
    	 */

        if (!$response) {
            return $this->__parseError(__('node has no templates', 'SolusVM'));
        }
        $td = [];
        foreach ($response['plans'] as $template) {
            if ($template['id'] == $template_id) {
                $td = $template;
                break;
            }
        }

        if (empty($td)) {
            return $this->__parseError(__('node has no templates', 'SolusVM'));
        }

        $template = [];
        $template['templateid'] = $td['id'];
        $template['templatename'] = $td['name'];
        $template['memory'] = $td['ram'] / 1024 / 1024;
        $template['diskspace'] = $td['disk'] / 1024 / 1024 / 1024;
        $template['cpucores'] = $td['cpus'];
        $template['bandwidth'] = $td['bandwidth'] / 1024 / 1024 / 1024;

        return $template;
    }

    /**
     * Get list of images from the VPS platform.
     *
     * @return array List of images
     */
    public function getImageList()
    {
        /*
    	 * Step 1) get images list
    	 */

      $response = $this->doCall(['action' => 'listtemplates', 'listpipefriendly' => true, 'type' => 'kvm']);
        /*
    	 * Step 2) provide feedback to WeFact
    	 */
        if ($response) {
            $images = [];
            $i = 0;
            // loop through images and build return array
            foreach (explode(',', $response['templateskvm']) as $image) {
                $image = explode('|', $image);
                $images[$i]['imageid'] = $image[0];
                $images[$i]['imagename'] = $image[1];
                $i++;
            }

            if (count($images) == 0) {
                return $this->__parseError(__('node has no images', 'SolusVM'));
            }

            return $images;
        }
        $this->__parseError($response['statusmsg']);

        return false;
    }

    /**
     * Validate the VPS server login credentials.
     *
     * @return bool True on success; False otherwise.
     */
    public function validateLogin()
    {
        $response = $this->doCall([]);
        if ($response['status'] == 'error') {
            return $response['statusmsg'];
        } else {
            return true;
        }
    }

    /**
     * This function makes it possible to provide additional settings or notes on the create and edit page of a VPS server within WeFact Hosting.
     * This may be necessary if more information is needed than just the URL, username and password of the platform.
     *
     * @param string $edit_or_show edit|show; determines if we are adding/editing or showing a VPS server
     *
     * @return string $html           input HTML
     */
    public function showSettingsHTML($edit_or_show = 'edit')
    {
        $html = '';

        if ($edit_or_show == 'show') {
            $html = '<strong class="title2">Node group (ID)</strong>'.
                     '<span class="title2_value">'.
                     ((isset($this->ServerSettings->NodeGroup)) ? htmlspecialchars($this->ServerSettings->NodeGroup) : '').
                     '</span>';
            $html .= '<strong class="title2">Client username</strong>'.
                     '<span class="title2_value">'.
                     ((isset($this->ServerSettings->ClientUser)) ? htmlspecialchars($this->ServerSettings->ClientUser) : '').
                     '</span>';
        } else {
            $html = '<strong class="title">Node group</strong>'.
             '<input type="text" name="module[vps][Settings][NodeGroup]" class="text1 size1" value="'.((isset($this->ServerSettings->NodeGroup)) ? htmlspecialchars($this->ServerSettings->NodeGroup) : '').'" />';
            $html .= '<strong class="title">Client username</strong>'.
             '<input type="text" name="module[vps][Settings][ClientUser]" class="text1 size1" value="'.((isset($this->ServerSettings->ClientUser)) ? htmlspecialchars($this->ServerSettings->ClientUser) : '').'" />';
        }

        return $html;
    }

    /**
     * Create a VPS on the VPS platform.
     *
     * @return array Return array with VPS ID on success; False on fail;
     */
    public function createVPS()
    {

        /*
    	 * Step 1) send create command
         *
    	 */
        $template = $this->getTemplate($this->TemplateID);
        $response = $this->doCall([
            'action'    => 'vserver-create',
            'type'      => 'kvm',
            'nodegroup' => $this->ServerSettings->NodeGroup,
            'hostname'  => $this->VPSName,
            'password'  => $this->Password,
            'username'  => $this->ServerSettings->ClientUser,
            'plan'      => $template['templatename'],
            'template'  => $this->Image,
            'ips'       => 1,
        ]);

        /*
    	 * Step 2) provide feedback to WeFact
    	 */
        if ($response && $response['status'] == 'success' && isset($response['vserverid'])) {
            $vps = [];
            $vps['id'] = $response['vserverid'];

            return $vps;
        } else {
            $this->__parseError($response['statusmsg']);

            return false;
        }
    }

    /**
     * Remove a VPS from the VPS platform.
     *
     * @param string $vps_id ID of the VPS on the VPS platform
     *
     * @return bool True on success; False otherwise.
     */
    public function delete($vps_id)
    {
        /*
    	 * Step 1) send delete command
         *
    	 */
        $response = $this->doCall([
            'action'       => 'vserver-terminate',
            'vserverid'    => $vps_id,
            'deleteclient' => false,
            ]);

        /*
    	 * Step 2) provide feedback to WeFact
    	 */
        if ($response && $response['status'] == 'success') {
            return true;
        } else {
            $this->__parseError($response['statusmsg']);

            return false;
        }
    }

    /**
     * Function to support multiple languages for return messages
     * use __('your message', 'YourName'); to translate a message based on the language of WeFact.
     *
     * @param string $language_code Language code
     */
    public function loadLanguageArray($language_code)
    {
        $_LANG = [];

        switch ($language_code) {
            case 'nl_NL':
                $_LANG['node gave no response'] = 'Server gaf geen antwoord terug.';
                $_LANG['node returned error'] = 'Server gaf een error terug.';
                $_LANG['node returned wrong data'] = 'Server gaf een antwoord terug, maar niet de benodigde data.';
                $_LANG['node has no images'] = 'Server heeft geen images';
                $_LANG['node has no templates'] = 'Server heeft geen templates';
            break;

            default: // In case of other language, use English
                $_LANG['node gave no response'] = 'No response from node';
                $_LANG['node returned error'] = 'Node returned an error.';
                $_LANG['node returned wrong data'] = 'Node returned incorrect data';
                $_LANG['node has no images'] = 'Node has no images';
                $_LANG['node has no templates'] = 'Node has no templates';
            break;
        }

        // Save to global array
        global $_module_language_array;
        $_module_language_array['SolusVM'] = $_LANG;
    }

    /**
     * Suspend or unsuspend a VPS on the VPS platform.
     *
     * @param string $vps_id ID of the VPS on the VPS platform
     * @param string $action suspend|unsuspend
     *
     * @return bool True on success; False otherwise.
     */
    public function suspend($vps_id, $action)
    {
        if (!$this->validateLogin()) {
            return false;
        }

        switch ($action) {
            case 'suspend':
                $response = $this->doCall([
                    'action'    => 'vserver-suspend',
                    'vserverid' => $vps_id,
                    ]);
            break;

            case 'unsuspend':
                $response = $this->doCall([
                    'action'    => 'vserver-unsuspend',
                    'vserverid' => $vps_id,
                    ]);
            break;
        }

        /*
    	 * Step 2) provide feedback to WeFact
    	 */
        if ($response && $response['status'] == 'success') {
            return true;
        } else {
            $this->__parseError($response['statusmsg']);

            return false;
        }
    }

    /**
     * Get details of a VPS by ID.
     *
     * @param string $vps_id ID of the VPS on the VPS platform
     *
     * @return array Array with VPS information
     */
    public function getVPSDetails($vps_id)
    {
        $info = $this->doCall([
            'action'    => 'vserver-info',
            'vserverid' => $vps_id,
            ]);
        if (!$info || $info['status'] != 'success' && isset($info['state'])) {
            $this->__parseError($info['statusmsg']);

            return false;
        }
        $infoall = $this->doCall([
            'action'    => 'vserver-infoall',
            'vserverid' => $vps_id,
            ]);
        if (!$infoall || $infoall['status'] != 'success' && isset($infoall['state'])) {
            $this->__parseError($infoall['statusmsg']);

            return false;
        }
        $vps_details = [];
        $vps_details['status'] = $this->__convertStatus($infoall['state']);
        $vps_details['hostname'] = $info['hostname'];
        $vps_details['ipaddress'] = $info['ipaddress']; // not required

        return $vps_details;
    }

    /**
     * Get details of a VPS by hostname.
     *
     * @param string $hostname Hostname of the VPS on the VPS platform
     *
     * @return array $vps            Array with VPS information
     */
     // TODO: Make this work
    public function getVPSByHostname($hostname)
    {
        return false;
    }

    /**
     * When a VPS is created from WeFact, this function is regularly called to check it's status.
     *
     * @param string $vps_id ID of the VPS on the VPS platform
     *
     * @return string active|building|error; Return status
     */
    public function doPending($vps_id)
    {
        return 'active';
    }

    /**
     * Use this function to convert VPS statusses from the VPS platform to a status WeFact can handle.
     *
     * @param string $status Status returned from a VPS platform command of a VPS
     *
     * @return string Converted status
     */
    private function __convertStatus($status)
    {
        switch ($status) {
            // VPS is active
            case 'online':
                $new_status = 'active';
            break;

            // VPS is paused
            case 'offline':
                $new_status = 'paused';
            break;

            default:
                $new_status = '';
            break;
        }

        return $new_status;
    }
}
