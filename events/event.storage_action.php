<?php

    require_once(TOOLKIT . '/class.event.php');
    require_once(EXTENSIONS . '/storage/lib/class.storage.php');

    Class eventstorage_action extends Event {

        public $ROOTELEMENT = 'storage-action';

        public static function about() {
            return array(
                'name' => 'Storage Action',
                'author' => array(
                    'name' => 'Büro für Web- und Textgestaltung',
                    'website' => 'http://hananils.de',
                    'email' => 'buero@hananils.de'),
                'trigger-condition' => 'storage-action'
            );
        }

        public static function allowEditorToParse() {
            return false;
        }

        public static function documentation() {
            return '<p>Storage offers three actions:</p>
<ul>
    <li><strong>set:</strong> to set new groups and items, replacing existing values</li>
    <li><strong>set-count:</strong> to set new groups and items, replacing existing values and recalculating counts</li>
    <li><strong>drop:</strong> to drop entire groups or single items from the storage</li>
</ul>
<p>These actions can be triggered by either sending a <code>POST</code> or <code>GET</code> request. This example form will update a shopping basket by raising the amount of <code>article1</code> by 3.</p>
<pre><code>&lt;form action=&quot;&quot; method=&quot;post&quot;&gt;
    &lt;input name=&quot;storage[basket][article1][count-positive]&quot; value=&quot;3&quot; /&gt;
    &lt;input name=&quot;storage-action[update]&quot; type=&quot;submit&quot; /&gt;
&lt;/form&gt;</code></pre>
<h3>Example Output</h3>
<pre><code>&lt;events&gt;
    &lt;storage-action type=&quot;set-count&quot; result=&quot;success&quot;&gt;
        &lt;request-values&gt;
            &lt;group id=&quot;basket&quot;&gt;
                &lt;item id=&quot;article1&quot; difference=&quot;+3&quot; /&gt;
            &lt;/group&gt;
        &lt;/request-values&gt;
    &lt;/storage-action&gt;
&lt;/events&gt;</code></pre>
<h3>Example Error Output</h3>
<pre><code>&lt;events&gt;
    &lt;storage-action type=&quot;set-count&quot; result=&quot;error&quot;&gt;
        &lt;message&gt;Storage could not be updated.&lt;/message&gt;
        &lt;message&gt;Invalid count: 3.5 is not an integer, ignoring value.&lt;/message&gt;
        &lt;request-values&gt;
            &lt;group id=&quot;basket&quot;&gt;
                &lt;item id=&quot;article1&quot; difference=&quot;+3.5&quot; /&gt;
            &lt;/group&gt;
        &lt;/request-values&gt;
    &lt;/storage-action&gt;
&lt;/events&gt;</code></pre>';
        }

        public function load() {
            if(isset($_REQUEST['storage-action'])) return $this->__trigger();
        }

        protected function __trigger() {
            $action_keys = array_keys($_REQUEST['storage-action']);
            $action = end($action_keys);

            $items = (array)$_REQUEST['storage'];
            array_walk_recursive($items, 'General::sanitize');

            $storage = new Storage();

            // Trigger action
            switch($action) {
                case 'set':
                    $storage->set($items);
                    break;
                case 'set-count':
                    $storage->setCount($items);
                    break;
                case 'drop':
                    $storage->drop($items);
                    break;
            }

            // Execute event
            return $this->execute($action, $items, $storage->getErrors());
        }

        public function execute($action, $items, $errors) {
            $result = new XMLElement($this->ROOTELEMENT);
            $result->setAttribute('type', $action);

            // Error
            if(!empty($errors)) {
                $result->setAttribute('result', 'error');
                foreach($errors as $error) {
                    $result->appendChild(new XMLElement('message', $error));
                }
            }

            // Success
            else {
                $result->setAttribute('result', 'success');
            }

            // Return request
            $request = new XMLElement('request-values');
            $result->appendChild($request);
            Storage::buildXML($request, $items, true);

            // Return result
            return $result;
        }

    }
