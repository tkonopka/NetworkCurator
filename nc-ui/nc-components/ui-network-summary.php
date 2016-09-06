<?php
/*
 * Page showing network summary
 * Abstract, authors, etc.
 * 
 * Assumes the array $netmeta was already obtained in nc-ui-network
 * 
 */

// re-fetch metadata, this time with extended content
$netmeta = $NCapi->getNetworkMetadata($network);
?>


<div class="row">
    <div class="col-sm-8">
        <h1><div id="nc-network-title" class="nc-editable-text nc-md" 
                 val="<?php echo $netmeta['network_title_id']; ?>">        
                     <?php echo $netmeta['network_title']; ?>
            </div></h1>

        <h4 class="nc-mt-10">Curators</h4>
        <?php echo ui_listnames($netmeta['curators']); ?>
        <h4 class="nc-mt-10">Authors</h4>
        <?php echo ui_listnames($netmeta['authors']); ?>
        <h4 class="nc-mt-10">Commentators</h4>
        <?php echo ui_listnames($netmeta['commentators']); ?>
        <hr/>

        <h3 class="nc-mt-10 nc-mb-10">Abstract</h3>
        <div id="nc-network-abstract" class="nc-editable-text nc-md"
             val="<?php echo $netmeta['network_abstract_id']; ?>"></div>
        <hr/>

        <h3 class="nc-mb-10">Description</h3>
        <div id="nc-network-content" class="nc-editable-text nc-md" 
             val="<?php echo $netmeta['network_content_id']; ?>"></div>
        <hr/>

        <div id="nc-comments" rootid="<?php echo $netmeta['network_content_id']; ?>"></div>
        <hr/>
        <div id="nc-newcomment" uid="<?php echo $uid; ?>" rootid="<?php echo $netmeta['network_content_id']; ?>"></div>

    </div>
</div>

<?php

foreach (["title", "content", "abstract"] as $i) {
    $netmd[$netmeta["network_" . $i . "_id"]] = $netmeta["network_" . $i];
}
?>
