/**
 * Functions for text editing (toolbar stuff)
 *
 * @todo most of the stuff in here should be revamped and then moved to toolbar.js
 * @author Andreas Gohr <andi@splitbrain.org>
 */

/**
 * Creates a toolbar button through the DOM
 * Called for each entry of toolbar definition array (built by inc/toolbar.php and extended via js)
 *
 * Style the buttons through the toolbutton class
 *
 * @param {string} icon      image filename, relative to folder lib/images/toolbar/
 * @param {string} label     title of button, show on mouseover
 * @param {string} key       hint in title of button for access key
 * @param {string} id        id of button, and '<id>_ico' of icon
 * @param {string} classname for styling buttons
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Michal Rezler <m.rezler@centrum.cz>
 */
function createToolButton(icon,label,key,id,classname){
    var $btn = jQuery(document.createElement('button')),
        $ico = jQuery(document.createElement('img'));

    // prepare the basic button stuff
    $btn.addClass('toolbutton');
    if(classname){
        $btn.addClass(classname);
    }

    $btn.attr('title', label).attr('aria-controls', 'wiki__text');
    if(key){
        $btn.attr('title', label + ' ['+key.toUpperCase()+']')
            .attr('accessKey', key);
    }

    // set IDs if given
    if(id){
        $btn.attr('id', id);
        $ico.attr('id', id+'_ico');
    }

    // create the icon and add it to the button
    if(icon.substr(0,1) !== '/'){
        icon = DOKU_BASE + 'lib/images/toolbar/' + icon;
    }
    $ico.attr('src', icon);
    $ico.attr('alt', '');
    $ico.attr('width', 16);
    $ico.attr('height', 16);
    $btn.append($ico);

    // we have to return a DOM object (for compatibility reasons)
    return $btn[0];
}

/**
 * Creates a picker window for inserting text
 *
 * The given list can be an associative array with text,icon pairs
 * or a simple list of text. Style the picker window through the picker
 * class or the picker buttons with the pickerbutton class. Picker
 * windows are appended to the body and created invisible.
 *
 * @param  {string} id    the ID to assign to the picker
 * @param  {Array}  props the properties for the picker
 * @param  {string} edid  the ID of the textarea
 * @return DOMobject    the created picker
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function createPicker(id,props,edid){
    // create the wrapping div
    var $picker = jQuery(document.createElement('div'));

    $picker.addClass('picker a11y');
    if(props['class']){
        $picker.addClass(props['class']);
    }

    $picker.attr('id', id).css('position', 'absolute');

    function $makebutton(title) {
        var $btn = jQuery(document.createElement('button'))
            .addClass('pickerbutton').attr('title', title)
            .attr('aria-controls', edid)
            .on('click', bind(pickerInsert, title, edid))
            .appendTo($picker);
        return $btn;
    }

    jQuery.each(props.list, function (key, item) {
        if (!props.list.hasOwnProperty(key)) {
            return;
        }

        if(isNaN(key)){
            // associative array -> treat as text => image pairs
            if (item.substr(0,1) !== '/') {
                item = DOKU_BASE+'lib/images/'+props.icobase+'/'+item;
            }
            jQuery(document.createElement('img'))
                .attr('src', item)
                .attr('alt', '')
                .css('height', '16')
                .appendTo($makebutton(key));
        }else if (typeof item == 'string'){
            // a list of text -> treat as text picker
            $makebutton(item).text(item);
        }else{
            // a list of lists -> treat it as subtoolbar
            initToolbar($picker,edid,props.list);
            return false; // all buttons handled already
        }

    });
    jQuery('body').append($picker);

    // we have to return a DOM object (for compatibility reasons)
    return $picker[0];
}

/**
 * Called by picker buttons to insert Text and close the picker again
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function pickerInsert(text,edid){
    insertAtCarret(edid,text);
    pickerClose();
}

/**
 * Add button action for signature button
 *
 * @param  {jQuery} $btn   Button element to add the action to
 * @param  {Array}  props  Associative array of button properties
 * @param  {string} edid   ID of the editor textarea
 * @return {string} picker id for aria-controls attribute
 * @author Gabriel Birke <birke@d-scribe.de>
 */
function addBtnActionSignature($btn, props, edid) {
    if(typeof SIG != 'undefined' && SIG != ''){
        $btn.on('click', function (e) {
            insertAtCarret(edid,SIG);
            e.preventDefault();
        });
        return edid;
    }
    return '';
}

/**
 * Determine the current section level while editing
 *
 * @param {string} textboxId   ID of the text field
 *
 * @author Andreas Gohr <gohr@cosmocode.de>
 */
function currentHeadlineLevel(textboxId){
    var field = jQuery('#' + textboxId)[0],
        s = false,
        opts = [field.value.substr(0,DWgetSelection(field).start)];
    if (field.form && field.form.prefix) {
        // we need to look in prefix context
        opts.push(field.form.prefix.value);
    }

    jQuery.each(opts, function (_, opt) {
        // Check whether there is a headline in the given string
        var str = "\n" + opt,
            lasthl = str.lastIndexOf("\n==");
        if (lasthl !== -1) {
            s = str.substr(lasthl+1,6);
            return false;
        }
    });
    if (s === false) {
        return 0;
    }
    return 7 - s.match(/^={2,6}/)[0].length;
}


/**
 * global var used for not saved yet warning
 */
window.textChanged = false;

/**
 * global var which stores original editor content
 */
window.doku_edit_text_content = '';
/**
 * Delete the draft before leaving the page
 */
function deleteDraft() {
    if (is_opera || window.keepDraft) {
        return;
    }

    var $dwform = jQuery('#dw__editform');

    if($dwform.length === 0) {
        return;
    }
    
    var id = $dwform.find('input[name=id]').val();    
    if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {     	
    	// remove a possibly saved draft using ajax
    	var fd = new FormData(); 
    	fd.append('call', 'draftdel'); 
    	fd.append('id', id); 
        navigator.sendBeacon(DOKU_BASE + 'lib/exe/ajax.php',fd);
        
        // remove lock
        var fd = new FormData(); 
    	fd.append('call', 'unlock'); 
    	fd.append('id', id); 
        navigator.sendBeacon(DOKU_BASE + 'lib/exe/ajax.php',fd);
        
    } else {
        jQuery.ajaxSetup({async: false}); //make last to jQuery calls synchronous, otherwise they get cancelled
        
        // remove a possibly saved draft using ajax
        jQuery.post(DOKU_BASE + 'lib/exe/ajax.php',
            {
                call: 'draftdel',
                id: id
            }
        );
        
        // remove lock
        jQuery.post(DOKU_BASE + 'lib/exe/ajax.php',
            {
                call: 'unlock',
                id: id
            }
        );
    }


    
}

/**
 * Activate "not saved" dialog, add draft deletion to page unload,
 * add handlers to monitor changes
 * Note: textChanged could be set by e.g. html_edit() as well
 *
 * Sets focus to the editbox as well
 */
jQuery(function () {
    var $editform = jQuery('#dw__editform');
    if ($editform.length == 0) {
        return;
    }

    var $edit_text = jQuery('#wiki__text');
    if ($edit_text.length > 0) {
        if($edit_text.attr('readOnly')) {
            return;
        }

        // set focus and place cursor at the start
        var sel = DWgetSelection($edit_text[0]);
        sel.start = 0;
        sel.end   = 0;
        DWsetSelection(sel);
        $edit_text.trigger('focus');

        doku_edit_text_content = $edit_text.val();
    }

    var changeHandler = function() {
        doku_hasTextBeenModified();

        doku_summaryCheck();
    };

    $editform.change(changeHandler);
    $editform.keydown(changeHandler);

    window.onbeforeunload = function(){
        if(window.textChanged) {
            return LANG.notsavedyet;
        }
    };
    window.onunload = deleteDraft;

    // reset change memory var on submit
    jQuery('#edbtn__save').on('click',
        function() {
            window.onbeforeunload = '';
            textChanged = false;
        }
    );
    jQuery('#edbtn__preview').on('click',
        function() {
            window.onbeforeunload = '';
            textChanged = false;
            window.keepDraft = true; // needed to keep draft on page unload
        }
    );

    var $summary = jQuery('#edit__summary');
    $summary.on('change keyup', doku_summaryCheck);

    if (textChanged) doku_summaryCheck();
});

/**
 * Updates textChanged variable if content of the editor has been modified
 */
function doku_hasTextBeenModified() {
    if (!textChanged) {
        var $edit_text = jQuery('#wiki__text');

        if ($edit_text.length > 0) {
            textChanged = doku_edit_text_content != $edit_text.val();
        } else {
            textChanged = true;
        }
    }
}

/**
 * Checks if a summary was entered - if not the style is changed
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 */
function doku_summaryCheck(){
    var $sum = jQuery('#edit__summary'),
        missing = $sum.val() === '';
    $sum.toggleClass('missing', missing).toggleClass('edit', !missing);
}
