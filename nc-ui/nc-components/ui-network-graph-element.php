<?php
/**
 * Forms to create a new node or a new link
 * 
 */
?>

<?php 
// block for displaying generic details about nodes/links
?>
<div id="nc-graph-details" class="nc-graph-details">
    [To do - this will display short info on the node/link]
    <h2><div id="nc-graph-details-title" class="nc-editable-text nc-md" val=""></div></h2>
    <div id="nc-graph-details-abstract" class="nc-editable-text nc-md" val=""></div>
</div> 

<?php
// next two blocks are forms for creating new nodes/links
?>
<div id="nc-graph-newnode" class="nc-graph-details">
    <h3>Create a new node</h1> 
        <form role="form" id="" onsubmit="nc.graph.createNode(); return false;">
            <div id="fg-nodename" class="form-group">
                <label>Node name:</label>        
                <input type="text" class="form-control" placeholder="Node name">                     
            </div>    
            <div id="fg-nodetitle" class="form-group">
                <label>Node title:</label>        
                <input type="text" class="form-control" placeholder="Node title">                     
            </div> 
            <div id="fg-nodeclass" class="form-group">
                <label>Node class:</label> 
                <div class="input-group-btn"></div>
            </div>  
            <button type="submit" class="btn btn-success nc-editor submit">Create</button>     
            <button type="button" onclick="nc.graph.removeNode();" class="btn btn-warning">Remove</button>
        </form>
</div>


<div id="nc-graph-newlink" class="nc-graph-details">
    <h3>Create a new link</h1> 
        <form role="form" id="" onsubmit="nc.graph.createLink(); return false;">
            <div id="fg-linkname" class="form-group">
                <label>Link name:</label>        
                <input type="text" class="form-control" placeholder="Link name">                         
            </div>    
            <div id="fg-linktitle" class="form-group">
                <label>Link title:</label>        
                <input type="text" class="form-control" placeholder="Link title">                 
            </div>     
            <div id="fg-linkclass" class="form-group">
                <label>Link class:</label>     
                <div class="input-group-btn"></div>
            </div>  
            <div id="fg-linksource" class="form-group">
                <label>Source:</label>
                <input type="text" class="form-control" disabled>
            </div>
            <div id="fg-linktarget" class="form-group">
                <label>Target:</label>
                <input type="text" class="form-control" disabled>
            </div>
            <button type="submit" class="btn btn-success nc-editor submit">Create</button>        
            <button type="button" onclick="nc.graph.removeLink();" class="btn btn-warning">Remove</button>
        </form>
</div>