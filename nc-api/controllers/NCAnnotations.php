<?php

/**
 * Class handling requests for annotations (update an annotation, create a new annotation)
 *
 * Class assumes that the NC configuration definitions are already loaded
 * Class assumes that the invoking user has passed identity checks
 *
 */
class NCAnnotations extends NCLogger {

    // db connection and array of parameters are inherited from NCLogger    
    // some variables extracted from $_params, for convenience
    private $_network;
    private $_netid;
    private $_uperm;

    /**
     * Constructor 
     * 
     * @param type $db
     * 
     * Connection to the NC database
     * 
     * @param type $params
     * 
     * array with parameters
     */
    public function __construct($db, $params) {

        // check for required parameters
        if (isset($params['network'])) {
            $this->_network = $params['network'];
        } else {
            throw new Exception("Missing required parameter network");
        }
        unset($params['network']);

        parent::__construct($db, $params);

        // find the network id, and user permissions
        $this->_netid = $this->getNetworkId($this->_network, true);
        $this->_uperm = $this->getUserPermissions($this->_netid, $this->_uid);
    }

    /**
     * processes request to update the text of an existing annotation
     * 
     * @return boolean
     * @throws Exception
     */
    public function updateAnnotationText() {

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, ["anno_id", "anno_text"]);

        // check if user has permission to view the table        
        if ($this->_uperm < NC_PERM_COMMENT) {
            throw new Exception("Insufficient permission to edit annotation");
        }

        $this->dblock([NC_TABLE_ANNOTEXT]);

        // need to find details of the existing annotation, user_id, root_id, etc.  
        $sql = "SELECT datetime, owner_id, root_id, parent_id, anno_type, network_id 
                  FROM " . NC_TABLE_ANNOTEXT . " 
                   WHERE network_id = ? AND anno_id = ? AND anno_status =" . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$this->_netid, $params['anno_id']]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Error retrieving current annotation");
        }
        $objectid = $result['parent_id'];
        $ownerid = $result['owner_id'];

        // curator can edit anything, but others can only edit their own items
        if ($this->_uperm < NC_PERM_CURATE) {
            if ($result['owner_id'] !== $this->_uid) {
                throw new Exception("Inconsistent annotation ownership");
            }
        }

        // for name annotation, make sure that new name is unique, i.e. not already used
        if ($result['anno_type'] == NC_NAME) {
            $sql = "SELECT datetime, root_id FROM " . NC_TABLE_ANNOTEXT . " WHERE 
                network_id = ? AND anno_type = " . NC_NAME . " 
                    AND anno_text = ? AND anno_status= " . NC_ACTIVE;
            $stmt = $this->qPE($sql, [$this->_netid, $params['anno_text']]);
            if ($stmt->fetch()) {
                throw new Exception("Name is not available");
            }
        }

        // if reached here, edit the annotation              
        $this->batchUpdateAnno([array_merge($params, $result)]);

        $this->dbunlock();

        // log the action       
        $this->logActivity($this->_uid, $this->_netid, "updated annotation text for", $params['anno_id'], $params['anno_text']);
        $this->sendUpdateAnnoEmail($objectid, [$ownerid], $result['anno_type']);

        return true;
    }

    /**
     * 
     * @return int
     * @throws Exception
     */
    public function createNewComment() {

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, ["user_id", "anno_text", "root_id", "parent_id"]);

        // check if user has permission to view the table          
        if ($this->_uperm < NC_PERM_COMMENT) {
            throw new Exception("Insufficient permission to edit annotation");
        }

        // determine the comment level from the parent id
        $annolevel = NC_COMMENT;
        $tat = "" . NC_TABLE_ANNOTEXT;

        if ($params['parent_id'] === '') {
            $params['parent_id'] = $this->_netid;
        } else {
            // check that the parent id is valid and active
            $sql = "SELECT anno_type FROM $tat WHERE 
                network_id = ? AND anno_id = ? AND anno_status = " . NC_ACTIVE;
            $stmt = $this->qPE($sql, [$this->_netid, $params['parent_id']]);
            $result = $stmt->fetch();
            if (!$result) {
                throw new Exception("Parent annotation does not exist");
            }
            if ($result['anno_type'] == NC_COMMENT) {
                $annolevel = NC_SUBCOMMENT;
            }
        }
        // check the root annotation is valid and active
        $sql = "SELECT anno_status, root_id, owner_id FROM $tat WHERE 
                network_id = ? AND anno_id = ? AND anno_status = " . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$this->_netid, $params['root_id']]);
        $rootdata = $stmt->fetch();
        if (!$rootdata) {
            throw new Exception("Root annotation does not exist");
        }

        // identify the owners of the parent and root annotations 
        $owners = array_unique([$rootdata['owner_id'],
            $this->getAnnoOwner($this->_netid, $params['parent_id'])]);

        // if reached here, insert the comment, log it, and finish
        $pp = array('network_id' => $this->_netid,
            'owner_id' => $this->_uid, 'user_id' => $this->_uid,
            'root_id' => $params['root_id'], 'parent_id' => $params['parent_id'],
            'anno_text' => $params['anno_text'], 'anno_type' => $annolevel);
        $newid = $this->insertAnnoText($pp);
        $this->logActivity($this->_uid, $this->_netid, 'wrote a comment', $newid, $params['anno_text']);
        $this->sendNewCommentEmail($rootdata['root_id'], $owners, $annolevel);

        return $newid;
    }

    /**
     * 
     * @return array
     * 
     * associative array with comments, ordered by level and date
     * 
     * @throws Exception
     */
    public function getComments() {

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, ["root_id"]);

        // fetch all the comments
        $sql = "SELECT datetime, modified, owner_id, user_id, anno_id, 
                       parent_id, anno_text 
                  FROM " . NC_TABLE_ANNOTEXT . "
                  WHERE network_id = ? and root_id = ? AND anno_status = " . NC_ACTIVE
                . " ORDER BY anno_type, datetime";
        $stmt = $this->qPE($sql, [$this->_netid, $params['root_id']]);
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['anno_id']] = $row;
        }

        return $result;
    }

    /**
     * @return array
     * 
     * associative array with annotations associated with a graph object.
     * 
     * This requires params to specify a root_id and the types of items to return.
     * The params called name, title, abstrat, content are here supposed to be booleans.
     * Set each to 1 to obtain that piece of data in the response, set 0 otherwise.
     * 
     */
    public function getSummary() {

        // check that required parameters are defined
        $params = $this->subsetArray($this->_params, array_merge(["root_id"], array_keys($this->_annotypes)));

        // start by getting the full summary data
        $result = $this->getFullSummaryFromRootId($this->_netid, $params['root_id'], true);

        // unset some of the items if they are not required
        foreach (array_keys($this->_annotypes) as $key) {
            if ($params[$key] != 1) {
                unset($result[$key]);
            } else {
                $result[$key] = $this->subsetArray($result[$key], ['owner_id', 'anno_id', 'anno_text']);
            }
        }

        // send the data back
        return $result;
    }

    /**
     * @return array
     * 
     * This requires params to specify a root_id for the annotation.
     * 
     * @return
     * 
     * array with all versions of the annotation
     * 
     */
    public function getHistory() {

        $params = $this->subsetArray($this->_params, ["anno_id"]);

        // fetch the annotation text from db
        $sql = "SELECT datetime, modified, user_id, anno_text 
                  FROM " . NC_TABLE_ANNOTEXT . "
                  WHERE network_id = ? and anno_id = ? ORDER BY modified DESC";
        $stmt = $this->qPE($sql, [$this->_netid, $params['anno_id']]);
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Send an email about a new comment. Send to network curators and the owner
     * of the comment or root object.
     * 
     * @param string $objectid
     * 
     * a code like Wxxxxx, Vxxxxxx, or Lxxxxxx for the object being commented on
     * 
     * @param string $objectowners
     * 
     * array with user names 
     * 
     * @param int $type
     * 
     * one of NC_COMMENT or NC_SUBCOMMENT
     *      
     */
    private function sendNewCommentEmail($objectid, $objectowners, $type) {

        // fetch the name of the annotated object
        $objectname = $this->getObjectName($this->_netid, $objectid)['anno_text'];

        $ncemail = new NCEmail($this->_db);
        $emaildata = ['NETWORK' => $this->_network,
            'OBJECT' => $objectname, 'OBJECTID' => $objectid,
            'USER' => $this->_uid];

        foreach ($this->_commenttypes as $key => $val) {
            if ($type == $val) {
                $emaildata['TYPE'] = $key;
            }
        };

        $ncemail->sendEmailToCurators("email-new-comment", $emaildata, $this->_netid, $objectowners);
    }

    /**
     * send an email to curators and object owner about an annotation update
     * 
     * @param string $objectid
     * 
     * identifier for the affects object, e.g. Nxxxxxx
     * 
     * @param array $objectowners
     * 
     * array with user names that should be included in the email
     * 
     * @param integer $type
     * 
     * one of NC_NAME, NC_TITLE, NC_ABSTRACT, NC_CONTENT
     * 
     * NOTE: perhaps the objectid checking could be done more elegantly here...
     */
    private function sendUpdateAnnoEmail($objectid, $objectowners, $type) {

        // fetch the name of the annotated object
        if ($type == NC_COMMENT || $type == NC_SUBCOMMENT) {
            // for a comment, need to fetch the parent/root id of node/link first
            $sql = "SELECT owner_id, root_id, parent_id FROM " . NC_TABLE_ANNOTEXT . "  
                WHERE network_id = ? AND anno_id = ? AND anno_status=" . NC_ACTIVE;
            $stmt = $this->qPE($sql, [$this->_netid, $objectid]);
            $result = $stmt->fetch();
            $objectid = $result['parent_id'];
            $objectowners[] = $result['owner_id'];
        }
        if ($type == NC_SUBCOMMENT) {
            // for a subcomment, need to fetch owner of main comment and owner of object
            $sql = "SELECT owner_id, root_id, parent_id FROM " . NC_TABLE_ANNOTEXT . "  
                WHERE network_id = ? AND anno_id = ? AND anno_status=" . NC_ACTIVE;
            $stmt = $this->qPE($sql, [$this->_netid, $objectid]);
            $result = $stmt->fetch();
            $objectid = $result['root_id'];
            $objectowners[] = $result['owner_id'];
        }
        $objectname = $this->getObjectName($this->_netid, $objectid)['anno_text'];

        $ncemail = new NCEmail($this->_db);
        $emaildata = ['NETWORK' => $this->_network,
            'OBJECT' => $objectname, 'OBJECTID' => $objectid,
            'USER' => $this->_uid];

        $atypes = array_merge($this->_annotypeslong, $this->_commenttypes);
        foreach ($atypes as $key => $val) {
            if ($type == $val) {
                $emaildata['TYPE'] = $key;
            }
        };

        $ncemail->sendEmailToCurators("email-update-anno", $emaildata, $this->_netid, $objectowners);
    }

}

?>