<?php
/*
 * Page showing/editing ontology for the network
 * 
 * Assumes some variables are already set by index.php
 * $uid, $network, $NCApi, $upermissions
 * 
 */

// get all the classes for the network
$nodeclasses = $NCapi->getNodeClasses($network);
$linkclasses = $NCapi->getLinkClasses($network);


?>

<h1 class="nc-mt-5">Ontology for network <?php echo $network; ?></h1>

<div class="row">
    <div class="col-sm-12">
        <h3 class="nc-mt-15">Nodes</h3>    
        <div id="nc-ontology-nodes">
        </div>
        <h3 class="nc-mt-15">Links</h3>
        <div id="nc-ontology-links">
        </div>

        <script>  
        <?php
        echo "var nc_node_classes=" . json_encode($nodeclasses) . ";";
        echo "var nc_link_classes=" . json_encode($linkclasses) . ";";
        ?>                            
        $(document).ready(
        function () {                   
            $('#nc-ontology-nodes').html(ncuiClassTreeWidget('<?php echo $network ?>', nc_node_classes, <?php echo $upermissions>3; ?>));                
            $('#nc-ontology-links').html(ncuiClassTreeWidget('<?php echo $network; ?>', nc_link_classes, <?php echo $upermissions>3; ?>));                          
        });            
        </script>
        
        <button class="btn btn-warning" onclick="javascript:ncTestOntology('<?php echo $network; ?>')">testing</button>
        
    </div>
</div>