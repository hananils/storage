# Storage

This Symphony extension creates a front-end storage using native PHP sessions.

Storage allows the creation of nested data arrays of any kind with three restrictions:

- The first key level in the storage array is considered the group name, this allows the creation of different storage contexts. For example a shopping cart and a list of user settings.
- Each item can contain a reserved key `count` which is used to store the amount of an item. Storage will automatically recalculate this count on update which is usefull for creating inventories.
- Each item can contain a reserved key `count-positive` which behaves exactly the same as `count` except that fact, that it doesn't allow negative values. This is usefull for creating shopping carts where clients are not supposed to order negative amount of items.

All count values must be integers, float values will be considered invalid and will be ignored.
If an item uses `count` and `count-positive` keys, `count` will take precedence and `count-positive` will be ignored.

## Events

Storage is a standalone class (`/lib/class.storage.php`) that can be used to create custom events. But the extension bundles a default event that should be sufficient for most cases. It offers four actions:

- **set:** to set new groups and items, replacing existing values
- **set-count:** to set new groups and items, replacing existing values and recalculating counts
- **drop:** to drop entire groups or single items from the storage
- **drop-all:** to drop the storage completely

These actions can be triggered by either sending a `POST` or `GET` request. This example form will update a shopping basket by raising the amount of `article1` by 3.

```html
<form action="" method="post">
	<input name="storage[basket][article1][count-positive]" value="3" />
	<input name="storage-action[set-count]" type="submit" />
</form>
```

### Example Output

```xml
<events>
    <storage-action type="set-count" result="success">
        <request-values>
            <group id="basket">
                <item id="article1">
                    <item id="count-postive">3</item>
                </item>
            </group>
        </request-values>
    </storage-action>
</events>
```

### Example Error Output

```xml
<events>
    <storage-action type="set-count" result="error">
    	<message>Storage could not be updated.</message>
    	<message>Invalid count: 3.5 is not an integer, ignoring value.</message>
        <request-values>
            <group id="basket">
                <item id="article1">
                    <item id="count-postive">3.5</item>
                </item>
            </group>
        </request-values>
    </storage-action>
</events>
```

## Data Sources

Storage also bundles a custom Data Source interface offering filtering by groups. If no filters have been specified, the Data source will return the full storage.

Optionally, it's possible to output the selected groups as parameters. Those output parameters will follow the Symphony naming convention of `$ds-` + `Data Source name` + `.` + `group name`, e. g. `$ds-storage.basket` and will contain the ids of the group's direct child items.

### XML Output

```xml
<storage>
    <group id="basket">
        <item id="article1" count="4" />
        <item id="article2" count="8" />
        <item id="article3" count="11" />
    </group>
</storage>

<ds-storage.basket>
    <item handle="article1">article1</item>
    <item handle="article2">article2</item>
    <item handle="article3">article3</item>
</ds-storage.basket>    
```

### Parameter Output

```xml
$ds-storage.basket: 'article1, article2, article3'
```

## Example: Shopping Cart with Product Variants

Say you'd like to create a shopping basket for a store that offers products in different colour variants. Each product should be shown on a separate page where the user can choose a colour and add the desired amount to the basket. A list of all available variants doesn't seem appropriate to you because there might be quite a lot of different colours. So you decide to have a select box with the colours and an input field for the amount:

```html
<form action="" method="post">
	<fieldset>
		<label>Colours
			<select>
				<option>red</option>
				<option>blue</option>
				<option>green</option>
			</select>
		</label>
		<label>Amount
			<input id="amount" type="text" name="?" value="1" />
		</label>
	</fieldset>
	<button type="submit" name="storage-action[set-count]">Add to basket</button>
</form>
```

Now you run into a problem: in order to connect colours and amounts, you need to have specific input names for each colour that store the chosen amount – `storage[basket][red][count]`, `storage[basket][blue][count]`, `storage[basket][green][count]`. This cannot be achieved with the given select box. In order to get your desired behaviour you need to create a different HTML structure first and apply your layout using JavaScript later on:

```html
<form action="" method="post">
	<fieldset>
		<legend>Colours</legend>
		<label><input type="text" name="storage[basket][red][count]" value="1" />red</label>
		<label><input type="text" name="storage[basket][blue][count]" value="1" />blue</label>
		<label><input type="text" name="storage[basket][green][count]" value="1" />green</label>
	</fieldset>
	<button type="submit" name="storage-action[set-count]">Add to basket</button>
</form>
```

Based on this markup you can now create your layout by iterating over the given colours and set the name of the amount field dynamically as soon as the user switches colours in the select box.