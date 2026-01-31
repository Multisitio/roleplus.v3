<?php
/* It's a class that makes it easier to create HTML forms */
class Kubox
{
    /**
     * @param content The content of the tag.
     * 
     * @return The content of the tag.
     */
	public static function content($content)
	{
        ob_end_clean();
        return $content;
    }

    /**
     * It creates a form submit button.
     * 
     * @param label The text that will be displayed on the button.
     * @param name The name of the button.
     * @param value The value of the button.
     * 
     * @return A string of HTML code.
     */
	public static function buttonSubmit($attrs='', $label='', $name='', $value='')
	{
        $default = "name=\"$name\"";
        $attrs = empty($attrs) ? $default : "$attrs $default";
        return Form::button($label, $attrs, 'submit', $value);
    }

    /**
     * It creates a form submit button with a background color.
     * 
     * @param label The text that will be displayed on the button.
     * @param name The name of the button
     * @param value The value of the button.
     * 
     * @return A button with the label, name, and value.
     */
	public static function buttonSubmitColor($attrs='', $label='', $name='', $value='')
	{
        $default = "data-bg_color";
        $attrs = empty($attrs) ? $default : "$attrs $default";
        return self::buttonSubmit($attrs, $label, $name, $value);
    }

    /**
     * It creates a checkbox with a label
     * 
     * @param checked The value of the checkbox.
     * @param label The label for the checkbox
     * @param name The name of the checkbox.
     * @param value The value of the checkbox.
     * 
     * @return The content of the ob_get_contents() function.
     */
	public static function checkbox($checked='', $label='', $name='', $value='')
	{
        ob_start();
        ?>
		<label>
            <?=Form::input('checkbox', $name, $checked, $value)?>
            <span><?=$label?></span>
        </label>
        <?php
        return self::content(ob_get_contents());
    }

    /**
     * It creates a dropzone for images
     * 
     * @param box the class of the label
     * @param label The label of the input.
     * @param name The name of the input field[].
     * @param dir The directory where the image is stored.
     * @param file The file to be uploaded.
     * 
     * @return The content of the buffer.
     */
	public static function dropimage($box='', $label='', $mini='', $name='', $dir='', $file='')
	{
        $box = empty($box) ? '' : " $box";
        $class = empty($file) ? '' : ' dropimagehover dropnocontent';
        ob_start();
        ?>
		<label<?=$box?>>
            <small><b><?=$label?>:</b></small>
            <div class="dropimage<?=$class?>">
                <input type="file" name="<?=$name?>">
                <?php if ($file): ?>
                    <img alt="IMG: <?=$file?>" src="<?="$dir$mini$file"?>" width="540px" height="540px">
                <?php endif; ?>
                <button type="button"><?=Html::img(t('Quitar imagen'), '/img/icons/x-square.svg', 30, 30)?></button>
                <input type="hidden" name="<?=$name?>" value="<?=$file?>">
            </div>
		</label>
        <?php
        return self::content(ob_get_contents());
    }

    /**
     * It creates a label with a text input.
     * 
     * @param attrs Any additional attributes you want to add to the input tag.
     * @param box the class of the label
     * @param label The label for the input.
     * @param name the name of the input field
     * @param placeholder The text that appears in the input field when it's empty.
     * @param type text, password, email, etc.
     * @param value The value of the input.
     * 
     * @return The input field.
     */
	public static function input($attrs='', $box='', $label='', $name='', $placeholder='', $type="text", $value='')
	{
        $placeholder .= '…';
        $placeholder = "placeholder=\"$placeholder\"";
        $attrs = empty($attrs) ? $placeholder : "$attrs $placeholder";
        $box = empty($box) ? '' : " $box";
        ob_start();
        ?>
		<label<?=$box?>>
            <small><b><?=$label?>:</b></small>
            <?=Form::input($type, $name, $attrs, $value)?>
        </label>
        <?php
        return self::content(ob_get_contents());
    }

    /**
     * It creates a link
     * 
     * @param attrs Any HTML attributes you want to add to the link.
     * @param label The text that will be displayed on the button.
     * @param href The URL to link to.
     * 
     * @return The link is being returned.
     */
	public static function link($attrs='', $label='', $href='')
	{
        if (str_starts_with($href, 'http')) {
            $default = 'rel="noopener noreferrer" target="_blank"';
            $attrs = empty($attrs) ? $default : "$attrs $default";
        }
        ob_start();
        ?>
		<a role="button" <?=$attrs?> href="<?=$href?>" title="<?=$label?>"><?=$label?></a>
        <?php
        return self::content(ob_get_contents());
    }

    /**
     * @param attrs the attributes you want to add to the link.
     * @param label The text that will be displayed on the button.
     * @param href The URL to link to.
     * 
     * @return A link with the data-bg_color attribute.
     */
	public static function linkColor($attrs='', $label='', $href='')
	{
        $attrs = empty($attrs) ? "data-bg_color" : "data-bg_color $attrs";
        return self::link($attrs, $label, $href);
    }

    /**
     * It creates a label with a select box inside
     * 
     * @param attrs Any additional attributes you want to add to the select box.
     * @param blank The text to display for the blank option.
     * @param box the class of the label
     * @param data an array of key/value pairs to be used as the options for the select box.
     * @param label The label for the form element
     * @param name The name of the select box
     * @param value The value of the input.
     * 
     * @return The contents of the ob_get_contents() function.
     */
	public static function select($attrs='', $blank='', $box='', $data='', $label='', $name='', $value='')
	{
        $box = empty($box) ? '' : " $box";
        ob_start();
        ?>
		<label<?=$box?>>
            <small><b><?=$label?>:</b></small>
            <?=Form::select($name, $data, $attrs, $value, $blank)?>
        </label>
        <?php
        return self::content(ob_get_contents());
    }

    /**
     * It creates a switch button
     * 
     * @param attrs Any additional attributes you want to add to the input tag.
     * @param checked if the switch is checked or not
     * @param label The text that will be displayed on the button.
     * @param name The name of the input field.
     * @param value The value of the checkbox.
     * 
     * @return The return value is the result of the ob_get_contents() function.
     */
	public static function switch($attrs='', $checked='', $label='', $name='', $value='')
	{
        $checked = empty($checked) ? '' : ' checked';
        $role = 'role="switch"' .  $checked;
        $attrs = empty($attrs) ? $role : "$role $attrs";
        ob_start();
        ?>
		<label class="button">
            <?=Form::input('checkbox', $name, $attrs, $value)?>
            <span><?=$label?></span>
        </label>
        <?php
        return self::content(ob_get_contents());
    }

    /**
     * It creates a textarea with a label
     * 
     * @param attrs Any additional attributes you want to add to the textarea.
     * @param grow if you want the textarea to grow as the user types, set this to true
     * @param label The label for the input.
     * @param name The name of the field.
     * @param placeholder The text that appears in the textarea when it's empty.
     * @param value The value of the textarea.
     * 
     * @return The textarea form element.
     */
	public static function textarea($attrs='', $box='', $grow='', $label='', $name='', $placeholder='', $value='')
	{
        $placeholder .= '…';
        $placeholder = "placeholder=\"$placeholder\"";
        $attrs = empty($attrs) ? "$placeholder " : "$attrs $placeholder ";
        $grow = empty($grow) ? '' : ' class="grow"';
        $box = empty($box) ? '' : " $box";
        ob_start();
        ?>
		<label<?="$box$grow"?>>
            <small><b><?=$label?>:</b></small>
            <textarea <?=$attrs?>name="<?=$name?>"><?=$value?></textarea>
        </label>
        <?php
        return self::content(ob_get_contents());
    }
}
