<?php

    /**
     * Storage class
     */
    class Storage {

        /**
         * The storage.
         *
         * @var array
         */
        private $_storage = array();

        /**
         * The error log.
         *
         * @var array
         */
        private $_errors = array();

        /**
         * Initialise storage, retaining existing data.
         */
        public function __construct() {
            session_start();
            $this->_storage = &$_SESSION['storage'];
            if(!is_array($this->_storage)) $this->_storage = (array)$this->_storage;
        }

        /**
         * Get storage data, optionally filtered by group.
         *
         * @param mixed $group
         *  Storage namespace, optional
         * @return array
         *  Associative storage array
         */
        public function get($group = null) {

            // Return filtered storage
            if(isset($group)) {
                return $this->_storage[$group];
            }

            // Return complete storage
            else {
                return $this->_storage;
            }
        }

        /**
         * Set storage data.
         *
         * @param array $items
         *  New data
         * @param boolean $recalculate
         *  If set to true, item counts will be recalculated
         */
        public function set($items = array(), $recalculate = false) {
            $items = $this->preprocessItems($this->_storage, $items, $recalculate);

            $storage = array_replace_recursive((array)$this->_storage, (array)$items);

            // Set storage
            if($storage !== null) {
                $this->_storage = $storage;
            }

            // Log error
            else {
                $this->_errors[] = 'Storage could not be updated.';
            }
        }

        /**
         * Set storage data and recalculate counts.
         *
         * @param array $items
         *  Data
         */
        public function setCount($items = array()) {
            $this->set($items, true);
        }

        /**
         * Drop given items from the storage.
         *
         * @param array $items
         *  The items that should be dropped
         **/
        public function drop($items = array()) {
            $this->_storage = $this->dropFromArray($this->_storage, $items);
        }

        /**
         * Drop key/value pairs from an existing array based on the keys
         * of a second array.
         *
         * @param array $array1
         *  The existing (e.g. session) array
         * @param array $array2
         *  The second (e.g. request data) array
         * @return array
         *  The updated array
         **/
        function dropFromArray($array1 = array(), $array2 = array()) {
            if(is_array($array2)) {
                foreach($array2 as $key => $value) {
                    if(is_array($value)) {
                        $array1[$key] = $this->dropFromArray($array1[$key], $value);
                    }
                    else{
                        unset($array1[$key]);
                    }
                }
            }
            return $array1;
        }

        /**
         * Drop all items from the storage.
         */
        public function dropAll() {
            $this->_storage = array();
        }

        /**
         * Get error.
         */
        public function getErrors() {
            return $this->_errors;
        }

        /**
         * Get groups.
         *
         * @return array
         *  Return all existing groups as array
         */
        public function getGroups() {
            return array_keys($this->_storage);
        }

        /**
         * Recalculate storage counts
         *
         * @param array $items
         *  The items array
         * @param array $storage
         *  The storage context
         * @param boolean $recalculate
         *  If set to true, item counts will be recalculated
         * @return array
         *  The processed items array
         */
        function preprocessItems($storage = array(), $items, $recalculate = false) {
            if(is_array($items)) {
                // Convention: 'count-positive' has precedence over 'count'
                if($items['count-positive']) {
                    unset($items['count']);
                }
                foreach($items as $key => $value) {
                    if(is_array($value)) {
                        $items[$key] = $this->preprocessItems($storage[$key], $value, $recalculate);
                    }
                    else {
                        $item_value = intval($value);
                        $stored_value = intval($storage['count']);
                        $add_value = $recalculate ? $stored_value : 0;

                        $is_int = ctype_digit((string)abs($value));

                        if($key == 'count' && $is_int === true) {
                            $items['count'] = $item_value + $add_value;
                        }
                        elseif($key == 'count-positive' && $is_int === true) {
                            $items['count'] = $this->noNegativeCounts($item_value + $add_value);
                        }
                        elseif(($key == 'count' || $key == 'count-positive') && $is_int === false) {
                            $this->_errors[] = "Invalid count: $items[$key] is not an integer, ignoring value.";
                            $items['count'] = $stored_value;
                        }
                        unset($items['count-positive']);
                    }
                }
            }
            return $items;
        }

        /**
         * No negative counts.
         *
         * @param int $count
         *  The count
         */
        private function noNegativeCounts($count) {
            if($count < 0) {
                return 0;
            }
            else {
                return $count;
            }
        }

        /**
         * Build groups based on an array of items and add all nested items.
         *
         * @param XMLElement $parent
         *  The element the items should be added to
         * @param array $items
         *  The items array
         * @param boolean $count_as_attribute
         *  If set to true, counts will be added as attributes
         */
        public static function buildXML($parent, $items, $count_as_attribute = false) {
            if(!is_array($items)) return;

            // Create groups
            foreach($items as $key => $values) {
                $group = new XMLElement('group');
                $group->setAttribute('id', $key);
                $parent->appendChild($group);

                // Append items
                Storage::itemsToXML($group, $values, $count_as_attribute);
            }
        }

        /**
         * Convert an array of items to XML, setting all counts as variables.
         *
         * @param XMLElement $parent
         *  The element the items should be added to
         * @param array $items
         *  The items array
         * @param boolean $count_as_attribute
         *  If set to true, counts will be added as attributes
         */
        public static function itemsToXML($parent, $items, $count_as_attribute = false) {
            if(!is_array($items)) return;

            foreach($items as $key => $value) {
                $item = new XMLElement('item');
                $item->setAttribute('id', General::sanitize($key));

                // Nested items
                if(is_array($value)) {
                    Storage::itemsToXML($item, $value, $count_as_attribute);
                    $parent->appendChild($item);
                }

                // Count as attribute
                elseif($key == 'count' && $count_as_attribute === true) {
                    if(empty($value)) $value = 0;
                    $parent->setAttribute('count', General::sanitize($value));
                }

                // Other values
                else {
                    $item->setValue(General::sanitize($value));
                    $parent->appendChild($item);
                }
            }
        }

    }