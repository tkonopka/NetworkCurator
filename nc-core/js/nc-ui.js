/* 
 * nc-ui.js
 * 
 * Functions that generate ui elements/widgets. 
 * In some cases the widgets come with events/methods. 
 * 
 * 
 */

if (typeof nc == "undefined") {
    throw new Error("nc is undefined");
}
nc.ui = {};



/* ==========================================================================
 * Constants that determine user experience
 * ========================================================================== */

// timeout used when animating some
nc.ui.timeout = 3000;



/* ==========================================================================
 * Permissions
 * ========================================================================== */

/**
 * create html with a form for updating user permissions
 *  
 * @param udata - json encoded array, each element should be an object describing 
 * one user
 */
nc.ui.PermissionsWidget = function(udata) {
            
    // internal function for making a button
    // val - 0-3 determines label on the button
    // uid - the user id (used to disactivate some buttons for the guest user)
    // perm - 0-3 permission level for this user
    // returns an <label></label> object
    function ncuiPB(val, uid, perm) {
        // convert a numeric value into a label        
        var vl = ["None", "View", "Comment", "Edit", "Curate"];
        var lab = vl[val];       
        if (perm==val) {
            perm = " active";
        } else {
            perm = "";
        }
        if (uid=="guest" && val>1) perm = " disabled";            
                
        // create a <label> html 
        var html = '<label class="btn btn-default nc-btn-permissions'+perm+'">';
        html += '<input type="radio" autocomplete="off" value="'+val+'" '+perm+'>';
        html += lab+'</label>';        
        return html;
    }
         
    var ans = $('<div></div>');
    $.each(udata, function(key, val){ 
        
        var uid = val['user_id'];        
        var up = val['permissions'];        
        var nowlab = uid;                
        if (uid != "guest") {            
            nowlab += " ("+val['user_firstname']+" "+val['user_middlename']+" "+val['user_lastname']+")";
        }
        
        // structure will be form > form-group with (label, btn-group, btn)
        var html = '<form class="form-inline nc-form-permissions" val="'+uid+'" onsubmit="return false;">';        
        html += '<div class="form-group" style="width:100%">';
        html += '<label class="col-form-label nc-fg-name">'+nowlab+'</label>';
        html += '<div class="btn-group" data-toggle="buttons">';                
        for (var pp=0; pp<5; pp++) 
            html += ncuiPB(pp, uid, up);   
        html += '</div>'; // closes the btn-group
        html += '<button class="btn btn-success" val="'+uid+'">Update</button>';        
        html += '</div></form>';                
        
        html = $(html);
        html.find('button').click(function() { 
            nc.users.updatePermissions(uid); 
        });
        
        // append to the main answer
        ans.append(html);
    });        
                
    return ans;                            
}
      

/* ==========================================================================
 * Ontology
 * ========================================================================== */

/**
 * create a bit of html with a form updating user permissions
 * 
 * netname - string with network name, assumed global variable
 * classdata - array with existing class structure
 * isnodes - boolean (true to populate node class tree, false to populate link tree)
 * readonly - boolean, true to simplify the tree and avoid editing buttons 
 * 
 */
nc.ui.ClassTreeWidget = function(classdata, islink) {
                    
    // get the root div for the treee
    var root = $('#nc-ontology-nodes');    
    if (islink) {
        root = $('#nc-ontology-links');        
    }                 
          
    // create a div for children and a new form
    var rootrow = {
        parent_id:'',
        class_id:'',
        class_name:'', 
        connector:+islink, 
        directional:0
    };
    //var parentsofroot = ncuiClassDisplay(rootrow, 0);
    var parentsofroot = '<ol class="nc-classtree-children" val="">'; 
    //rootrow['class_name']='';    
    parentsofroot += '</ol>';        
    parentsofroot += nc.ui.ClassForm(rootrow);    
    root.append(parentsofroot);
        
    // set up drag-drop of classes     
    var oldContainer;
    root.find(".nc-classtree-children").sortable({
        handle: 'button.nc-btn-move',
        afterMove: function (placeholder, container) {
            if(oldContainer != container){
                if(oldContainer)
                    oldContainer.el.removeClass("droptarget");
                container.el.addClass("droptarget");

                oldContainer = container;                                       
            }
        },
        onDrop: function ($item, container, _super) {
            container.el.removeClass("droptarget");            
            _super($item, container);      
            $item.addClass('aftermove');
            setTimeout(function() {
                $item.removeClass('aftermove');
            }, 1500);
        }
    });
     
    // populate the ontology tree
    // uses multiple passes to make all classes display regardless of the order
    // in which they appear in the array    
    do {
        var addagain = 0;
        $.each(classdata, function(key, val){                                        
            addagain += nc.ui.addClassTreeRow(val);          
        });        
    } while (addagain<Object.keys(classdata).length);
                
    // create functions that respond to events on the tree
    // submitting a new class
    root.delegate("form.nc-classcreate", "submit", function() {
        var parentid=$(this).attr('val');        
        var newclassname = $(this).find("input").val();            
        var isdirectional = +$(this).find("input.form-check-input").is(":checked");        
        nc.ontology.createClass(parentid, newclassname, islink, isdirectional); 
    });  
    // clicking to edit an existing class
    root.delegate("div.nc-classdisplay button[val='edit']", "click", function() {                        
        var classid = $(this).parent().attr('val');
        // make sure input box shows current classname
        var classname = $(this).parent().find('.nc-classdisplay-span').html();        
        var thisform = root.find("form.nc-classupdate[val='"+classid+"']");
        thisform.find('input').val(classname);
        // toggle visibility
        thisform.toggle();        
        root.find("div.nc-classdisplay[val='"+classid+"']").toggle();        
    });    
    // clicking to remove a class
    root.delegate("div.nc-classdisplay button[val='remove']", "click", function() {                                
        var classid = $(this).parent().attr('val');                      
        nc.ontology.removeClass(classid);
    });
    // clicking to cancel updating an existing class
    root.delegate("form.nc-classupdate .nc-btn-class-cancel", "click", function() {                                
        var classid = $(this).attr('val');              
        root.find("div.nc-classdisplay[val='"+classid+"']").toggle();
        root.find("form.nc-classupdate[val='"+classid+"']").toggle();                
    });
    // clicking to update the value of an existing class
    root.delegate("form.nc-classupdate .nc-btn-class-update", "click", function() {        
        var thisform = $(this).parent().parent();
        var classid=thisform.attr('val');
        var parentid = thisform.parent().parent().attr('val');        
        var newclassname = thisform.find("input").val();            
        var islink = thisform.find("input.form-check-input").length>0;
        var isdirectional = 0+thisform.find("input.form-check-input").is(":checked");        
        nc.ontology.updateClassProperties(classid, newclassname, parentid, islink, isdirectional);        
        root.find("div.nc-classdisplay[val='"+classid+"']").toggle();
        root.find("form.nc-classupdate[val='"+classid+"']").toggle();                
    });    
    
    // after all the tree is populated, only display a small number of elements    
    //root.find("form.nc-classupdate").hide();    
    root.find("div.nc-classdisplay").show();    
    root.find("form.nc-classcreate[val='']").show();    
}


/**
 * Creates one row in a class tree
 * Row consists of a div with a label and a div below that will hold children
 * 
 * @param classrow - array with components class_id, etc. (see ClassForm)
 */
nc.ui.ClassTreeRowWidget = function(classrow) {
    
    // create objects for displaying, editing, and adding subclasses
    var adisplay = nc.ui.ClassDisplay(classrow);    
    var aform = nc.ui.ClassForm(classrow);
    var achildren = '<ol class="nc-classtree-children" val="'+classrow['class_id']+'"></ol>';       
    
    // create the widget from the components
    return '<li val="'+classrow['class_id']+'">'+adisplay + aform + achildren+'</li>';                
}


/**
 * Creates html displays one row in the classtree (when viewing only)
 * 
 * @param classrow - array with details on this class 
 * 
 */
nc.ui.ClassDisplay = function(classrow) {
        
    // create a div with one label (possible a directional comment) and one button
    var fg = '<div val="'+classrow['class_id']+'" class="nc-classdisplay"><span class="nc-classdisplay-span">'+classrow['class_name']+'</span>';
    // forms for links include a checkbox for directional links    
    fg += '<span class="nc-classdisplay-span nc-directional">';
    if (+classrow['directional']) {
        fg+= ' (directional)';
    }
    fg+='</span>';     
    if (nc.curator) {
        fg += '<button val="remove" class="pull-right btn btn-primary btn-sm nc-btn-remove">Remove</button>';           
        fg += '<button val="edit" class="pull-right btn btn-primary btn-sm nc-btn-edit">Edit</button>';   
        fg += '<button val="move" class="pull-right btn btn-primary btn-sm nc-btn-move">Move</button>';       
    }
    fg += '</div>'; 
                
    return fg;
}

/**
 * Creates a form that asks the user for a new class name and shows a submit button
 * 
 * parentid - name of parent class (or empty if root)
 * islink, isdirectional - settings for link class configuration
 * classname - name of existing class (or empty if new class)
 */
nc.ui.ClassForm = function(classrow) {
    
    if (!nc.curator) {
        return "";
    }
    
    var classid = classrow['class_id'];    
    var classname = classrow['class_name'];
    var islink = +classrow['connector'];
    var directional = +classrow['directional'];
        
    var formclass = 'nc-classupdate';
    if (classname=='') {
        formclass = 'nc-classcreate';
    } 
    
    // create the form
    var ff ='<form val="'+classid+'" style="display: none" class="form-inline nc-class-form '+formclass+'" onsubmit="return false">';
    // create the textbox asking for the classname
    var fg = '<div class="form-group"><input class="form-control input-sm" placeholder="Class name"';
    if (classname!='') {
        fg+= 'value="'+classname+'"';
    }
    fg += '></div>';        
    // forms for links include a checkbox for directional links
    if (islink) {        
        fg += '<div class="form-group"><label class="form-check-inline"><input type="checkbox" class="form-check-input"';
        if (directional) {
            fg+= ' checked';
        }
        fg+='>Directional</label></div>';        
    }
    // buttons to create a new class or update a class name
    if (classname=='') {
        fg += '<div class="form-group"><button class="btn btn-success btn-sm submit">';
        fg += 'Create new class</button></div>';                       
    } else {
        fg += '<div class="form-group">';
        fg+= '<button val="'+classid+'" class="btn btn-primary btn-sm nc-btn-class-update">Update</button>';
        fg+= '<button val="'+classid+'" class="btn btn-primary btn-sm nc-btn-class-cancel">Cancel</button>';
        fg += '</div>';                           
    }
    var ff2 = '</div></form>';
       
    return ff+fg+ff2;    
}


/**
 * Create a row in a class tree and insert it into the page
 * 
 * @return boolean
 * 
 * true if the row was successfully insert
 * false if not (e.g. if the attempt is made before the parent is in the dom)
 */
nc.ui.addClassTreeRow = function(classrow) {  
         
    // find the root of the relevant tree    
    var root = $('#nc-ontology-nodes');    
    if (+classrow['connector']) {        
        root = $('#nc-ontology-links');        
    }  
       
    // check if this class already exists
    if (root.find('li[val="'+classrow['class_id']+'"]').length>0) {
        return true;
    }
       
    // find the target div where to insert the node
    var parentid = classrow['parent_id'];    
    var targetdiv = root.find('ol.nc-classtree-children[val="'+parentid+'"]');            
    if (targetdiv.length==0) {
        return false;
    }
    
    // create the widget for this class
    var newobj = $(nc.ui.ClassTreeRowWidget(classrow));
    newobj.hide();
    
    // figure out whether to insert before the form or at the end    
    if (targetdiv.children("form.nc-classcreate").length > 0) {         
        $(newobj).insertBefore(targetdiv.children('form.nc-classcreate'));
    } else {               
        targetdiv.append(newobj);
    }   
    targetdiv.find("li").show('normal');
    targetdiv.find("div.nc-classdisplay").show('normal');    
    
    return true;
}



/* ==========================================================================
 * Log
 * ========================================================================== */

/**
 * Create a toolbar for the activity log
 */
nc.ui.ActivityLogToolbar = function(logsize, pagelen) {    
    var html = '<ul class="pagination">';
    var numpages = logsize/pagelen;
    
    for (var i=0; i<numpages; i++) {
        html += '<li value='+i+'><a href="javascript:nc.loadActivity('+i+', '+pagelen+')">'+(i+1)+'</a></li>';
    }
    html += '</ul><div id="nc-log-contents"></div>';
     
    return(html);     
}

/**
 * @param data - an array of arrays
 * each element in array should hold data on one row in the log table
 */
nc.ui.populateActivityArea = function(data) {
    var ans = '';
    $.each(data, function(key, val){        
        ans += nc.ui.OneLogEntry(val);
    });    
    $('#nc-log-contents').html(ans);
}

/**
 * Provides html to write out one line in the log table
 */
nc.ui.OneLogEntry = function(data) {
    var html = '<div class="media nc-log-entry">';    
    html += '<span class="nc-log-entry-date">'+data['datetime']+' </span>';
    html+= '<span class="nc-log-entry-user">'+data['user_id']+' </span>';
    html += '<span class="nc-log-entry-action">'+data['action']+' </span>';
    html += '<span class="nc-log-entry-target">'+data['target_name']+' </span>';
    if (data['value'].length>0) {
        html += '<span class="nc-log-entry-value">('+data['value']+')</span>';
    }
    html += '</div>';        
    return html;    
}



/* ==========================================================================
 * Generic, i.e. small-scale widgets
 * ========================================================================== */

/**
 * Create a toolbar button group
 * 
 * @param aa array of strings to place in the button group
 */
nc.ui.ButtonGroup = function(aa) {
    
    var html = '<div class="btn-group nc-toolbar-group nc-toolbar-group-new" role="group">';
    for (var i in aa) {
        html += '<button class="btn btn-primary" val="'+aa[i]+'">'+aa[i]+'</button>';
    }
    html += '</div>';
    
    return $(html);
}

/**
 * Create a button with a dropdown list
 * 
 * @param atype string prefix 
 * @param aa array, each element is assumed to contain a label and val
 * @param aval string placed in button val field 
 * (use this to distinguish one dropdown from another) 
 * 
 */
nc.ui.DropdownButton=function(atype, aa, aval) {
        
    var caret = '<span class="pull-right caret"></span>';
    
    var html = '<div class="btn-group nc-toolbar-group nc-toolbar-group-new" role="group">';    
    html += '<div class="btn-group" role="group">';        
    html += '<button class="btn btn-primary dropdown-toggle" val="'+aval+'" selection="'+aa[0].val+'" data-toggle="dropdown"><span class="pull-left nc-classname-span">'+atype+' '+aa[0].label+'</span>'+caret+'</button>';  
    html += '<ul class="dropdown-menu">';
    for (var i in aa) {
        html += '<li><a val="'+aa[i].val+'" href="#">' + aa[i].label + '</a></li>'
    }
    html += '</ul>';
    html += '</div></div>'; // this closes dropdown and btn-group
    
    // create object
    var dropb = $(html);
    
    // attach handlers for the dropdown links
    dropb.find("a").click(function() {
        // find the text and add it        
        var nowval = $(this).attr("val");
        var p4 = $(this).parent().parent().parent().parent();
        p4.find('button.dropdown-toggle').html('<span class="pull-left nc-classname-span">'+atype+' '+nowval +'</span>'+ caret)
        .addClass('active').attr("selection", nowval); 
        $(this).dropdown("toggle");
        return false;
    });       
    
    return dropb;
}



/* ==========================================================================
 * Curation
 * ========================================================================== */

/**
 * Create a div with a toolbox for curation/editing
 * This is used to show/edit page elements (e.g. abstacts) as well as bodies
 * of comments.
 *
 *
 */
nc.ui.AnnoEditBox = function() {
        
    // write static html to define components of the toolbox
    var html = '<div class="nc-curation-box">';
    html += '<div class="nc-curation-toolbox" style="display: none">';
    html += '<a role="button" class="nc-curation-toolbox-md btn btn-sm btn-default" >Edit</a>';
    html += '<a role="button" class="nc-curation-toolbox-preview btn btn-sm btn-default">Preview</a>';    
    html += '<a role="button" class="nc-curation-toolbox-close pull-right">close</a>';
    html += '</div><div class="nc-curation-content"></div>';
    html += '<textarea class="nc-curation-content" style="display: none"></textarea>';
    html += '<a role="button" class="btn btn-sm btn-success nc-save" style="display: none">Save</a>';
    html += '<a role="button" class="btn btn-sm btn-danger nc-remove" style="display: none">Remove</a></div>';    
        
    // create DOM objects, then add actions to the toolbox buttons
    var curabox = $(html);        

    // clicking pen/edit/md hides the div and shows raw md in the textarea
    curabox.find('a.nc-curation-toolbox-md').click(function() { 
        var thiscurabox = $(this).parent().parent();
        thiscurabox.find('div.nc-curation-content').hide();
        thiscurabox.find('textarea,a.btn-success').show();                                
    });    
    // clicking preview converts textarea md to html, updates the md object in the background
    curabox.find('a.nc-curation-toolbox-preview').click(function() {  
        var thiscurabox = $(this).parent().parent();
        var annomd = thiscurabox.find('textarea').hide().val();                              
        thiscurabox.find('div.nc-curation-content').html(nc.mdconverter.makeHtml(annomd)).show();
    });    
    // clicking save sends the md to the server    
    curabox.find('a.nc-save').click(function() {  
        var thiscurabox = $(this).parent();
        var annomd = thiscurabox.find('textarea').val(); 
        var annoid = thiscurabox.parent().attr("val");
        nc.updateAnnotationText(annoid, annomd);
    });    
    // clicking close triggers preview and makes the toolbox disappear
    curabox.find('a.nc-curation-toolbox-close').click(function() { 
        var thiscurabox = $(this).parent().parent();
        thiscurabox.find('a.nc-save').hide("normal");        
        thiscurabox.find('div.nc-curation-toolbox').hide();
        thiscurabox.find('a.nc-curation-toolbox-preview').click();        
    });        
    //var allcontents = ;    
    curabox.find('.nc-curation-content').on("click" , function() {        
        var thiscurabox = $(this).parent();                
        if (thiscurabox.parent().hasClass("nc-editable-text-visible")) {
            thiscurabox.find('.nc-curation-toolbox').show('normal');
            thiscurabox.find('div.nc-curation-content').hide();
            thiscurabox.find('textarea, a.nc-save').show();            
        }        
    });
   
    return curabox;
}



/* ==========================================================================
 * Comments
 * ========================================================================== */

/**
 * Creates a box to display a comment or type in a comment
 */
nc.ui.CommentBox = function(uid, rootid, parentid, annoid, annomd) {
    
    // determine if this is a primary comment or a response to a previous comment
    var commentclass = "nc-comment-response";
    if (rootid==parentid) {
        commentclass = "nc-comment-primary";
    } 
    
    var html = '<div class="media" val="'+annoid+'">';    
    html += '<a class="media-left">';
    html += '<img class="media-object '+commentclass+'" src="nc-data/users/'+uid+'.png"></a>';  
    html += '<div class="media-body" val="'+annoid+'"></div></div>';
    
    var commentbox = $(html);
    var commentbody = commentbox.find('.media-body');
    if (annomd=='') {
        commentbody.append('<div><span class="nc-log-entry-user">Write a new comment</span></div>');
    }
    commentbody.append(nc.ui.AnnoEditBox());
    if (annomd=='') {        
        // this is a blank comment, i.e. an invitation to create a new comment
        commentbody.find('.nc-curation-toolbox,textarea').show();        
        commentbody.find('.nc-curation-toolbox-close').hide();                
        commentbody.find('a.nc-save').off("click").show()
        .click(function() {        
            var annotext = $(this).parent().find("textarea").val();
            nc.createComment(annotext, rootid, parentid);
        });
    }
    
    // if this is a main comment, add a link to reply to the comment
    // if this is a subcomment, skip this step
    if (rootid==parentid && annomd!='') {
        var rhtml = '<a val="'+annoid+'" class="nc-comment-response">Respond to the comment</a>';
        rhtml = $(rhtml);
        rhtml.click(function() {            
            var responsebox = nc.ui.CommentBox(nc.userid, rootid, annoid, '', '');            
            $(this).hide().parent().append(responsebox);
            responsebox.find('a.nc-save').off("click").show()
            .click(function() {                
                var annotext = $(this).parent().find("textarea").val();
                nc.createComment(annotext, rootid, annoid);
            })
        })
        commentbody.append(rhtml);
    }
    
    
    return commentbox;    
}



nc.ui.addCommentBox = function(datetime, ownerid, rootid, parentid, annoid, annotext) {
    
    var cbox = $('#nc-comments');   
    
    var html = '<div class="nc-mb-5"><span class="nc-log-entry-date">'+datetime+'</span>';
    html += '<span class="nc-log-entry-user">'+ownerid+'</span></div>';    
    var commentbox = nc.ui.CommentBox(ownerid, rootid, parentid, annoid, annotext);    
    commentbox.find('textarea').html(annotext);
    commentbox.find('.media-body a.nc-curation-toolbox-preview').click();                        
    commentbox.find('.media-body').prepend(html).addClass("nc-editable-text").attr("val", annoid);  
    
    if (rootid==parentid) {
        cbox.append(commentbox);    
    } else {
        commentbox.insertBefore ($('.media-body a[val="'+parentid+'"]'));
    }    
    
    // when a new comment is added live, the date is null, make animation
    if (datetime=='just now') {
        commentbox.hide().show('normal');            
    }
}

/**
 * 
 */
nc.ui.populateCommentsBox = function(commentarray) {        
    var rootid = $('#nc-comments').attr("val");        
    $.each(commentarray, function(key, val){                          
        nc.ui.addCommentBox(val['datetime'], val['owner_id'], rootid, val['parent_id'], 
            val['anno_id'], val['anno_text']);                   
    })
}