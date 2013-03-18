<?php

    require_once TOOLKIT . '/class.datasource.php';
    require_once FACE . '/interface.datasource.php';
    require_once EXTENSIONS . '/storage/lib/class.storage.php';

    Class StorageDatasource extends DataSource implements iDatasource {

        public static function getName() {
            return __('Storage');
        }

        public static function getClass() {
            return __CLASS__;
        }

        public function getSource() {
            return self::getClass();
        }

        public static function getTemplate(){
            return EXTENSIONS . '/storage/templates/blueprints.datasource.tpl';
        }

        public function settings() {
            $settings = array();

            $settings[self::getClass()]['params'] = $this->dsParamPARAMS;
            $settings[self::getClass()]['groups'] = implode(', ', (array)$this->dsParamGROUPS);

            return $settings;
        }

    /*-------------------------------------------------------------------------
        Utilities
    -------------------------------------------------------------------------*/

        /**
         * Returns the source value for display in the Datasources index
         *
         * @param string $file
         *  The path to the Datasource file
         * @return string
         */
        public function getSourceColumn($handle) {
            return 'Storage';
        }

    /*-------------------------------------------------------------------------
        Editor
    -------------------------------------------------------------------------*/

        public static function buildEditor(XMLElement $wrapper, array &$errors = array(), array $settings = null, $handle = null) {
            $settings = $settings[self::getClass()];

            $fieldset = new XMLElement('fieldset');
            $fieldset->setAttribute('class', 'settings contextual ' . __CLASS__);
            $fieldset->appendChild(new XMLElement('legend', self::getName()));

            // Groups
            $label = new XMLElement('label', __('Filter by Groups') . '<i>' . __('Optional') . '</i>');
            $input = Widget::Input('fields[' . self::getClass() . '][groups]', $settings['groups']);
            $label->appendChild($input);
            $fieldset->appendChild($label);

            // Suggest existing groups
            $storage = new Storage();
            $groups = $storage->getGroups();

            if(!empty($groups)) {
                $tags = new XMLElement('ul', null, array('class' => 'tags'));
                foreach($groups as $group) {
                    $tags->appendChild(new XMLElement('li', $group));
                }
                $fieldset->appendChild($tags);
            }

            // Output parameters
            $input = Widget::Input('fields[' . self::getClass() . '][params]', '1', 'checkbox');
            if(intval($settings['params']) == 1) {
                $input->setAttribute('checked', 'checked');
            }
            $label = Widget::Label();
            $label->setValue(__('%s Output groups as parameters', array($input->generate())));
            $fieldset->appendChild($label);

            $wrapper->appendChild($fieldset);
        }

        public static function validate(array &$settings, array &$errors) {
            return true;
        }

        public static function prepare(array $settings, array $params, $template) {
            $settings = $settings[self::getClass()];

            // Groups
            $groups = explode(',', $settings['groups']);
            if(!empty($groups)) {
                foreach($groups as $group) {
                    if(trim($group) == '') continue;
                    $string .= "\t\t\t'" . trim($group) . "'," . PHP_EOL;
                }
                $template = str_replace('<!-- GROUPS -->', trim($string), $template);
            }

            // Return template with settings
            return sprintf($template,
                $params['rootelement'],
                $settings['params']
            );
        }

    /*-------------------------------------------------------------------------
        Execution
    -------------------------------------------------------------------------*/

        public function grab(array &$param_pool = null) {
            $result = new XMLElement($this->dsParamROOTELEMENT);
            $storage = new Storage();
            $groups = array();

            // Get groups
            if(!empty($this->dsParamGROUPS)) {
                foreach($this->dsParamGROUPS as $id) {
                    $groups[$id] = $storage->get($id);
                }
            }
            else {
                $groups = $storage->get();
            }

            // Build XML
            Storage::buildXML($result, $storage->get(), true);

            // Add output parameters
            if(intval($this->dsParamPARAMS) == 1 && is_array($groups)) {
                foreach($groups as $name => $values) {
                    $param_pool['ds-' . $this->dsParamROOTELEMENT . '.' . $name] = array_keys((array)$values);
                }
            }

            return $result;
        }
    }

    return 'StorageDatasource';
