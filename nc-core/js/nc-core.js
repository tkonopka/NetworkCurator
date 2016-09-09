/* 
 * nc-core.js
 *  
 * 
 * 
 */


/**
 * nc is the main object/namespace for the NetworkCurator
 * 
 * The nc namespace holds data relevant to the current user and the current page.
 * 
 * Sub-namespaces hold functions relevant for manipulating objects, dealing
 * with the user interface, or communicating with the server.
 *   
 */  
var nc = {
    // initialize the object with the current username
    userid: '',
    firstname: '',
    middlename: '',
    lastname: '',
    // the current network name
    network: '',
    // objects holding markdown and comment content
    md: {},
    comments: {},
    // settings for permissions
    commentator: 0,
    curator: 0,
    editor: 0, 
    // location of server-side api
    api: window.location.href.split('?')[0]+"nc-core/networkcurator.php",   
    // sub-namespaces
    admin: {},    
    classes: {},    
    graph: {},
    init: {},
    permissions: {},    
    users: {},
    ui: {},
    utils: {}    
};




/* ==========================================================================
 * User interface & interactions
 * ========================================================================== */

// markdown converter
nc.mdconverter = new showdown.Converter({
    headerLevelStart: 1, 
    tables: true,
    tasklists: true
});


/* ==========================================================================
* Generic functions 
* ========================================================================== */

/**
* Show a message in a modal window
*/
nc.msg = function(h, b) {
    $('#nc-msg-header').html(h);
    $('#nc-msg-body').html(b);
    $('#nc-msg-modal').modal('show');
}


/* ==========================================================================
 * Startup
 * ========================================================================== */

/**
 * runs several startup functions. Each function determines if its content
 * is relevant for the current page and either returns quickly or performs its
 * startup duties.
 */
nc.init.all = function() {
    nc.init.initPermissions();
    nc.init.initLog();
    nc.init.initMarkdown();
    nc.init.initComments();    
    nc.init.initOntology();
    nc.init.initCuration();
    nc.init.initGraph();       
}

/**
 * Invoked at page startup - builds widgets for guest and user permissions 
 */
nc.init.initPermissions = function() {       
    // check if this is the permissions page
    var guestperms = $('#nc-permissions-guest');
    var usersperms = $('#nc-permissions-users');
    if (guestperms.length==0 || usersperms.length==0) {
        return;
    }
    // creat widgets
    guestperms.append(nc.ui.PermissionsWidget(nc.permissions.guest));                
    usersperms.append(nc.ui.PermissionsWidget(nc.permissions.users));                          
    // prevent disabled buttons being clicked
    $('.btn-group .disabled').click(function(event) {
        event.stopPropagation();
    }); 
}

/**
 * Invoked at startup to generate ontology trees
 * 
 */
nc.init.initOntology = function() {
    // check if this function applies on the page
    var ontnodes = $('#nc-ontology-nodes');
    var ontlinks = $('#nc-ontology-links');    
    if (ontnodes.length==0 || ontlinks.length==0) {
        return;
    }
    // add ontology trees        
    ontnodes.html(nc.ui.ClassTreeWidget(nc.ontology.nodes, false));                    
    ontlinks.html(nc.ui.ClassTreeWidget(nc.ontology.links, true));                                 
}

/** 
 * Run at page startup to create a log widget with buttons and log content
 * 
 */
nc.init.initLog = function() {  
    // check if the log div is present 
    var logdiv = $('#nc-activity-log');
    if (logdiv.length==0) {
        return;
    }
      
    // fetch the total number of rows and a first set of log data
    $.post(nc.api, 
    {
        controller: "NCNetworks", 
        action: "getActivityLogSize", 
        network_name: nc.network        
    }, function(data) {  
        data = $.parseJSON(data);
        var logsize = +data['data'];
        logdiv.append(nc.ui.ActivityLogToolbar(logsize, 50));           
        nc.loadActivity(0, 50);
    });  
}

/**
 * Initialize a graph editing toolbar and graph viewer
 */
nc.init.initGraph = function() {

}

/**
* run at startup, fetches comments associated with the nc-comments box.
*/
nc.init.initComments = function() {
        
    // find out if this page has a space for comments
    var cbox = $('#nc-comments');
    if (cbox.length==0) {
        return;
    }
        
    // fetch all comments    
    $.post(nc.api, 
    {
        controller: "NCAnnotations", 
        action: "getComments", 
        network_name: nc.network, 
        root_id: cbox.attr("val")
    }, function(data) {        
        nc.utils.alert(data);  
        data = $.parseJSON(data);
        if (!data['success']) {  
            nc.msg("Hey!", "Got error response from server: "+data['data']);  
            return;
        }        
        // populate the comments box with the 
        nc.ui.populateCommentsBox(data['data']);        
    });
    
    // for users allowed to generate comments, add a comment box    
    if (!nc.commentator) {
        return;
    }
    
    var rootid = $('#nc-newcomment').attr('val');
    $('#nc-newcomment').html(nc.ui.CommentBox(nc.userid, rootid, rootid, '', ''));
}

/**
 * For all users, sets up boxes that display markdown content. 
 * For curators, provides access to page toggling
 * 
 */
nc.init.initCuration = function() {
    
    // all users need to have the anno edit boxes
    // these boxes actually displays content.     
    var box = nc.ui.AnnoEditBox();
    var alleditable = $('.nc-editable-text');    
    alleditable.html(box);    
    
    // other functions are for curators only    
    if (!nc.curator) {
        return;
    }
    
    // show curation level ui graphics, add an event to toggle curation on/off
    $('.nc-curator').show();    
    var lockbtn = $('#nc-curation-lock');
    lockbtn.on("click",
        function() {
            lockbtn.find('i.fa-lock, i.fa-unlock').toggleClass('fa-lock fa-unlock');
            lockbtn.toggleClass("nc-editing nc-looking");            
            $('.nc-editable-text').toggleClass('nc-editable-text-visible');            
        });    
    
    $('.nc-curation-toolbox').css("font-size", $('body').css("font-size"));   
}

/**
* run at startup to convert data in a global object nc_md into html
* within page elements
*/
nc.init.initMarkdown = function() {   
       
    // convert the nc_md content into html, assign to a target div
    $.each(nc.md, function(key, val) {                  
        var temp = $('div .nc-md[val="'+key+'"]');
        temp.find('textarea.nc-curation-content').html(val);
        temp.find('div.nc-curation-content').html(nc.mdconverter.makeHtml(val));
    });        
}


/* ==========================================================================
* Actions on Log page
* ========================================================================== */

/**
* Invoked from the log page when user requests a page of the log
* 
* @param pagenum - integer, page number of log to load 
* (i.e. 0 to get first page of the log, 1 to skip the first entries and show the
* next batch, etc)
* @param pagelen - integer, number of entries per page of the log 
* 
*/
nc.loadActivity = function(pagenum, pagelen) {    
    
    $('#nc-activity-log li[value!='+pagenum+']').removeClass("active");
    $('#nc-activity-log li[value='+pagenum+']').addClass("active");
   
    $.post(nc.api, 
    {
        controller: "NCNetworks", 
        action: "getNetworkActivity", 
        network_name: nc.network,
        offset: pagenum*pagelen,
        limit: pagelen
    }, function(data) {                 
        data = $.parseJSON(data);
        if (data['success']) {
            nc.ui.populateActivityArea(data['data']);
        } else {
            nc.msg(data['data']);
        }
    });

}



/* ==========================================================================
* Annotation updates
* ========================================================================== */

/**
* This sends an update request to the server
* 
* Uses nc_network - network name in global variable
* Uses nc_md - array of md components 
*/
nc.updateAnnotationText = function(annoid, annomd) {
    
    if (typeof nc_network == "undefined") {        
        return;
    }    
        
    // send a request to the server
    $.post(nc.api, 
    {
        controller: "NCAnnotations", 
        action: "updateAnnotationText", 
        network_name: nc_network,
        anno_id: annoid,
        anno_text: annomd
    }, function(data) {        
        nc.utils.alert(data);  
        data = $.parseJSON(data);
        if (!data['success']) {  
            nc.msg("Hey!", "Got error response from server: "+data['data']);            
        }
    });

}


/* ==========================================================================
* Commenting
* ========================================================================== */

/**
* run at startup to show a box where the current user can write a new comment
*/
nc.showNewCommentBox = function() {
    
}

/**
* run when user presses "save" and tries to submit a new comment
*/
nc.createNewComment = function(annomd, rootid, parentid) {
        
    if (typeof nc_network == "undefined") return; 
    
    // avoid sending a comment that is too short
    if (annomd.length<2) exit();        

    // provide click feedback
    $('#nc-newcomment a.nc-save').removeClass('btn-success').addClass('btn-default')
    .html('Sending...');
        
    var timeout = this.timeout;
        
    // send a request to the server
    $.post(nc.api, 
    {
        controller: "NCAnnotations", 
        action: "createNewComment", 
        network_name: nc_network, 
        root_id: rootid,
        parent_id: parentid,
        anno_text: annomd
    }, function(data) {        
        nc.utils.alert(data);  
        data = $.parseJSON(data);
        if (!data['success']) {  
            nc.msg("Hey!", "Got error response from server: "+data['data']);            
        }
        // provide feedback in the button
        $('#nc-newcomment a.nc-save').html('Done');        
        setTimeout(function(){
            $('#nc-newcomment a.nc-save').addClass('btn-success').removeClass('btn-default').html('Save');
        }, timeout);         
        // add the comment to the page
        nc.ui.addCommentBox('just now', nc_uid, rootid, parentid, 
            data['data'], annomd);
        if (rootid!=parentid) {
            $('.media-body .media[val=""]').hide();    
        }
        $('#nc-newcomment textarea').val('');
    });

}


/* ==========================================================================
* Actions on page load
* ========================================================================== */

/**
* This fixes a "bug" in bootstrap that allows radio buttons to accept clicks
* and change active state even if they are set to disabled
*/
$(document).ready(
    function () {
        nc.init.all();
        //alert("DL2");
        // call a function that will perform startup relevant for curating content
        nc.curationStartup();                   
        //alert("DL3");
        // perform md markup
        nc.showMarkdown();
        //alert("DL4");
        // fetch all the comments
        nc.loadComments();
        //alert("DL5");
        nc.loadLog();
        // display the commenting box
        nc.showNewCommentBox();       
    //alert("DL6");
    });



/* ==========================================================================
* misc code, leftovers
* ========================================================================== */
