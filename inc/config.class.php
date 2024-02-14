<?php
/**
 * ---------------------------------------------------------------------
 * ITSM-NG
 * Copyright (C) 2022 ITSM-NG and contributors.
 *
 * https://www.itsm-ng.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of ITSM-NG.
 *
 * ITSM-NG is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * ITSM-NG is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ITSM-NG. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */
use ScssPhp\ScssPhp\Compiler;

class PluginWhitelabelConfig extends CommonDBTM {
    /**
     * Displays the configuration page for the plugin
     * 
     * @return void
     */
    public function showConfigForm() {
        if (!Session::haveRight("plugin_whitelabel_whitelabel",UPDATE)) {
            return false;
        }

        $colors = $this->getThemeColors();
        $field_labels = [
            'primary_color' => __('Primary Color'),
            'secondary_color' => __('Secondary Color'),
            'primary_text_color' => __('Primary Text Color'),
            'secondary_text_color' => __('Secondary Text Color'),
            'header_background_color' => __('Header Background Color'),
            'header_text_color' => __('Header Text Color'),
            'nav_background_color' => __('Nav Background Color'),
            'nav_text_color' => __('Nav Text Color'),
            'nav_submenu_color' => __('Nav Submenu Color'),
            'nav_hover_color' => __('Nav Hover Color'),
            'favorite_color' => __('Favorite Color'),
        ];

        $form = [
            'action' => Plugin::getWebDir("whitelabel")."/front/config.form.php",
            'buttons' => [
                [
                    'name' => 'update',
                    'type' => 'submit',
                    'value' => __('Save'),
                    'class' => 'btn btn-secondary'
                ],
                [
                    'name' => 'reset',
                    'type' => 'submit',
                    'value' => __('Reset'),
                    'class' => 'btn btn-secondary'
                ]
            ],
            'content' => [
                __('Colors') => [
                    'visible' => true,
                    'inputs' => []
                ],
                __('Files') => [
                    'visible' => true,
                    'inputs' => [
                        sprintf(__('Favicon (%s)', 'whitelabel'), Document::getMaxUploadSize()) => [
                            'id' => 'FavoriteIconFilePicker',
                            'name' => 'favicon',
                            'type' => 'file',
                            'value' => '',
                            'accept' => '.ico'
                        ],
                        sprintf(__('Logo (%s)', 'whitelabel'), Document::getMaxUploadSize()) => [
                            'id' => 'LogoFilePicker',
                            'name' => 'logo_central',
                            'type' => 'file',
                            'value' => '',
                            'accept' => '.png'
                        ],
                        sprintf(__('Import your CSS configuration (%s)', 'whitelabel'), Document::getMaxUploadSize()) => [
                            'id' => 'CssFilePicker',
                            'name' => 'css_configuration',
                            'type' => 'file',
                            'value' => '',
                            'accept' => '.css'
                        ],
                    ]
                ]
            ]
        ];
        foreach ($field_labels as $name => $title) {
            $form['content'][__('Colors')]['inputs'][$title] = [
                'name' => $name,
                'type' => 'color',
                'value' => $colors[$name],
                'col_lg' => 3,
                'col_md' => 4,
            ];
        }
        renderTwigForm($form);
    }

     /**
     * Get the primary theme color
     *
     * @return array
     */
    private function getThemeColors() {
         //use class to select on table 
         $config = new table_glpi_plugin_whitelabel_brand();
         $row=$config->select();
         //if result
         if (count($row) > 0) {
              foreach ($row as $k=>$v){
                 //if the field is a color
                 if (substr($v,0,1) == '#')
                     $colors[$k]=$v;
              }
         }else{//no color value on table use default values
             $default_value_css = new plugin_whitelabel_const();
             $colors = $default_value_css->all_value();
         }
         return $colors;
    }

    public function handleWhitelabel($reset = false) {
        //use class to colors values
        $default_value_css = new plugin_whitelabel_const();
        //use class to use table
        $sql = new table_glpi_plugin_whitelabel_brand();

        if ($reset) {
            foreach ($default_value_css->all_value() as $k=>$v){
                $data[$k]=$v;
            }
            $sql -> update($data);
            $this->handleClear("favicon");
            $this->handleClear("logo_central");
            $this->handleClear("css_configuration");
        } else {
            $fields = $default_value_css->all_value();
            foreach($fields as $key => $val){
                //if post value exist
                if (isset($_POST[$key])){
                    //put it on array
                    $data[$key] = $_POST[$key];
                }
            }
            //update on database color fields values
            if (isset($data))
                $sql -> update($data);
        }
        $message="";
        $files_to_upload = array("favicon" => array("image/x-icon", "image/vnd.microsoft.icon"),
                                 "logo_central" => array("image/png"),
                                 "css_configuration" => array("text/css"));
        foreach ($files_to_upload as $k=>$v)
            $message .= $this->handleFile($k, $v);
        
        if ($message != ""){
            Session::addMessageAfterRedirect("<font color=red><b>".$message."</b></font>", 'whitelabel');
        }

        if (file_exists(Plugin::getPhpDir("whitelabel")."/bak/favicon.ico.bak")) {
            copy(Plugin::getPhpDir("whitelabel")."/bak/favicon.ico.bak", GLPI_ROOT."/pics/favicon.ico");
        }

        if(file_exists(Plugin::getPhpDir("whitelabel")."/uploads/favicon.ico")) {
            copy(Plugin::getPhpDir("whitelabel")."/uploads/favicon.ico", GLPI_ROOT."/pics/favicon.ico");
        } 
    }

    /**
     * Generate and install new CSS sheets w/ styles mapped
     */
    public function refreshCss($reset = false) {

        $default_value_css = new plugin_whitelabel_const();        
        $css_default_values=$default_value_css->all_value();
        $all_fields_color = $default_value_css->all_value_split();
        //we need logo_central central field pour testing is exist or not
        $all_fields_color[] = "logo_central";
        $sql = new table_glpi_plugin_whitelabel_brand();
        if ($reset) {
            $row=$css_default_values;
        } else {
            $row=$sql->select($all_fields_color);        
        }

        foreach ($row as $k=>$v){
            $map["%".$k."%"] = $v;
        }
        //tab <address to put css>=><scss modele>
        $style_css= [
            GLPI_ROOT."/css/custom.scss"=>'template.scss',
            //GLPI_ROOT."/css/whitelabel_login.css"=>'login_template.scss'
        ];

        foreach ($style_css as $k=>$v){
            //if a old css file exist => unlink
            if(file_exists($k))
                unlink($k);
            //scss
            $template = file_get_contents(Plugin::getPhpDir("whitelabel")."/styles/".$v);
            // Interpolate SCSS
            $style = strtr($template, $map);
            if (isset($map['css_configuration']) && $map['css_configuration'] != ""){
                $style += "\n@import url('".$map['css_configuration']."');\n";
            }
            if (isset($map['%logo_central%']) && $map['%logo_central%'] != ""){
                $style .= "\n\$logo-file: url('"."../plugins/whitelabel/uploads/".$map['%logo_central%']."');\n";
            }
            file_put_contents($k, $style);
            //change chmod
            chmod($k, 0664);
        }
    }

    /**
     * Handles file upload actions
     */
    private function handleFile(string $file, array $formats) {

        if(empty($_FILES[$file]) || !isset($_FILES[$file])) {
            return;
        }

        // Get error code from file upload action
        switch ($_FILES[$file]["error"]) {
            case UPLOAD_ERR_OK:
                if (!in_array($_FILES[$file]["type"], $formats)) {
                    return "Only images of mime types: ".implode($formats)." are supported for $file files!";
                    exit();
                }
                $this->createDirectoryIfNotExist(Plugin::getPhpDir("whitelabel", true)."/uploads/");
                $ext = pathinfo($_FILES[$file]["name"], PATHINFO_EXTENSION);
                $uploadfile = Plugin::getPhpDir("whitelabel", true)."/uploads/".$file.".".$ext;

                if(file_exists($uploadfile)) {
                    unlink($uploadfile);
                }

                if (move_uploaded_file($_FILES[$file]["tmp_name"], $uploadfile)) {
                    $sql = new table_glpi_plugin_whitelabel_brand();
                    $sql-> update(array($file => $file.".".$ext));   
                    chmod($uploadfile, 0664);
                }
                break;
            case UPLOAD_ERR_INI_SIZE:
                $message = "The uploaded $file file exceeds the upload_max_filesize directive in php.ini";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $message = "The uploaded $file file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = "The uploaded $file file was only partially uploaded";
                break;
            case UPLOAD_ERR_NO_FILE:
                //$message = "No $file file was uploaded";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = "Missing a temporary folder";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = "Failed to write $file file to disk";
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = "File upload stopped by extension";
                break;

            default:
                $message = "Unknown upload error";
                break;
        }
        if (isset($message))
            return $message;
        return false;
    }

    /**
     * Creates a directory in the specified path, returns false if it fails
     *
     * @param string $path The path to the folder to create
     * @return bool
     */
    private function createDirectoryIfNotExist(string $path) {
        if (!file_exists($path)) {
           mkdir($path, 0664);
        } elseif (!is_dir($path)) {
            return false;
        }
        return true;
    }

    private function handleClear(string $field) {
        //if checkbox selected to delete file
        $sql = new table_glpi_plugin_whitelabel_brand();
        //check this file exist
        $row=$sql-> select($field);   
        if (isset($row[$field])){
            //unlink file
            if (isset($row[$field]) && $row[$field] != "" && file_exists(Plugin::getPhpDir("whitelabel")."/uploads/".$row[$field]))
                unlink(Plugin::getPhpDir("whitelabel")."/uploads/".$row[$field]);
            //update table
            $sql-> update(array($field=>''));  
            return true; 
        }            
        return false;
    }
}
