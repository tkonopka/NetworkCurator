<?php

include_once "NCDB.php";
include_once "NCTimer.php";

/*
 * Class handling logging activity into the _activity and _log tables.
 * It also forms the basis for all the controllers.
 * 
 * Functions assume that the NC configuration definitions are already loaded
 * 
 */

class NCLogger extends NCDB {

    // class inherits _db from NCCacher
    // here define other parameters
    protected $_params; // parameters passed on by the user
    protected $_uid; // user_id (or guest)
    protected $_upw; // user_confirmation code (or guest)   
    // some constant for getting annotations types
    protected $_annotypes = ["name" => NC_NAME, "title" => NC_TITLE,
        "abstract" => NC_ABSTRACT, "content" => NC_CONTENT];
    protected $_annotypeslong = ["name" => NC_NAME, "title" => NC_TITLE,
        "abstract" => NC_ABSTRACT, "content" => NC_CONTENT, "defs" => NC_DEFS];
    
    /**
     * Constructor with connection to database
     * 
     * @param PDO $db 
     * 
     */
    public function __construct($db, $params) {
        parent::__construct($db);
        $this->_params = $params;
        if (isset($params['user_id'])) {
            $this->_uid = $params['user_id'];
        } else {
            throw new Exception("Missing required parameter user_id");
        }
    }

    /**
     * Create an id string that is not already present in a dbtable table
     * 
     * @param type $dbtable
     * 
     * name of table in database to query
     * 
     * @param type $idcolumn
     * 
     * column in dbtable holding ids.
     * 
     * @param type $idprefix
     * 
     * prefix for random id - e.g. to make Nxxxxxx for nodes or Lxxxxxx for links
     * 
     * @param type $stringlength
     * 
     * @return
     */
    protected function makeRandomID($dbtable, $idcolumn, $idprefix, $stringlength) {
        $newid = "";
        $keeplooking = true;
        while ($keeplooking) {
            $newid = $idprefix . makeRandomString($stringlength);
            $sql = "SELECT $idcolumn FROM $dbtable WHERE $idcolumn='$newid'";
            $keeplooking = $this->_db->query($sql)->fetch();
        }
        return $newid;
    }

    /**
     * 
     * @param type $dbtable
     * @param type $idcolumn
     * @param type $idprefix
     * @param type $stringlength     
     * 
     * @param array $n
     * 
     * number of requested viable random ids      
     * 
     * @return string
     */
    protected function makeRandomIDSet($dbtable, $idcolumn, $idprefix, $stringlength, $n) {
        $keeplooking = true;
        while ($keeplooking) {
            // create a batch of new ids
            $newids = array_pad([""], $n, "");
            for ($i = 0; $i < $n; $i++) {
                $newids[$i] = $idprefix . makeRandomString($stringlength);
            }
            // encode the ids into a query against the db
            $whereids = implode("%' OR $idcolumn LIKE '", $newids);
            $whereclause = " WHERE $idcolumn LIKE '" . $whereids . "%'";
            $sql = "SELECT $idcolumn FROM $dbtable $whereclause";
            $stmt = $this->qPE($sql, []);
            $keeplooking = $stmt->fetch();
        }
        return $newids;
    }

    /**
     * Add entry into log table
     * 
     * @param string $userid
     * @param string $userip
     * @param string $action
     * @param string $value
     * @throws Exception
     * 
     */
    public function logAction($userid, $userip, $controller, $action, $value) {
        //if ($this->_log) {
        // prepare a statement for log-logging
        $sqllog = "INSERT INTO " . NC_TABLE_LOG . "
                    (datetime, user_id, user_ip, controller, action, value) VALUES
                    (UTC_TIMESTAMP(), :user_id, :user_ip, :controller, :action, :value)";
        $stmt = $this->_db->prepare($sqllog);
        // execture the query with current parameters
        $pp = array('user_id' => $userid, 'user_ip' => $userip,
            'controller' => $controller, 'action' => $action,
            'value' => $value);
        $stmt->execute($pp);
        //}
        return 1;
    }

    /**
     * Add entry into activity table
     * 
     * @param type $uid
     * @param type $netid
     * @param type $action
     * @param type $targetid
     * @param type $value
     * @throws Exception
     */
    public function logActivity($userid, $netid, $action, $target, $value, $maxlen = 128) {

        // perhaps shorten the $value field
        if (strlen($value) > ($maxlen - 4)) {
            $value = substr($value, $maxlen) . "...";
        }
        if (strlen($target) > ($maxlen - 4)) {
            $target = substr($target, $maxlen) . "...";
        }
        
        // prepare a statement for activity-logging
        $sql = "INSERT INTO " . NC_TABLE_ACTIVITY . "
                    (datetime, user_id, network_id, action, target_name, value)
                    VALUES
                    (UTC_TIMESTAMP(), :user_id, :network_id, :action,
                    :target_name, :value)";
        $pp = array('user_id' => $userid, 'network_id' => $netid,
            'action' => $action, 'target_name' => $target, 'value' => $value);
        $this->qPE($sql, $pp);

        return 1;
    }

    /**
     * Execute a db query that insert an annotation into the table. 
     * 
     * The action insert one row with status set to active.
     * 
     * @param array $params
     * 
     * the array should have entries: 
     * network_id, owner_id, user_id, root_id, parent_id, anno_text, anno_type
     * 
     * @return 
     * 
     * id code for the new annotation
     * 
     */
    public function insertAnnoText($params) {

        // get a new annotation id
        $tat = "" . NC_TABLE_ANNOTEXT;
        $newid = $this->makeRandomID($tat, 'anno_id', NC_PREFIX_TEXT, NC_ID_LEN);
        $params['anno_id'] = $newid;

        // insert the annotation into the table
        $sql = "INSERT INTO $tat
                    (datetime, network_id, owner_id, user_id, root_id, parent_id,
                    anno_id, anno_text, anno_type, anno_status) VALUES
                    (UTC_TIMESTAMP(), :network_id, :owner_id, :user_id, :root_id, :parent_id,
                    :anno_id, :anno_text, :anno_type, " . NC_ACTIVE . ")";
        $this->qPE($sql, $params);

        return $newid;
    }

    /**
     * @deprecated
     * 
     * Updates the annotext table with some new data.
     * Updating means changing an existing row with anno_id to status=OLD,
     * and inserting a new row with status = active. 
     * 
     * @param array $params
     * 
     * The function assumes the array contains exactly these elements.
     * network_id, user_id, root_id, parent_id, anno_id, anno_text, anno_type
     * 
     * @return
     * 
     * integer 1 upon success
     *      
     * @deprecated
     */
    protected function updateAnnoText($params) {

        throw new Exception("deprecated in favor of batchUpdateAnno");

        $params = $this->subsetArray($params, ["network_id", "datetime", "owner_id",
            "root_id", "parent_id", "anno_id", "anno_text", "anno_type"]);
        $params['user_id'] = $this->_uid;

        $tat = "" . NC_TABLE_ANNOTEXT;

        // set existing annotions as OLD, This becomes the historical record
        $sql = "UPDATE $tat SET anno_status = " . NC_OLD . "
                    WHERE network_id = ? AND anno_id = ? AND anno_status = " . NC_ACTIVE;
        $this->qPE($sql, [$params['network_id'], $params['anno_id']]);

        // insert a new  copy. This becomes the current version
        $sql = "INSERT INTO $tat
                    (datetime, modified, network_id, owner_id, user_id, root_id, parent_id,
                    anno_id, anno_text, anno_type, anno_status) VALUES
                    (:datetime, UTC_TIMESTAMP(), :network_id, :owner_id, :user_id, :root_id, :parent_id,
                    :anno_id, :anno_text, :anno_type, " . NC_ACTIVE . ")";
        $this->qPE($sql, $params);
    }

    /**
     * Updates the annotext table with some new data.
     * Updating means changing an existing row with anno_id to status=OLD,
     * and inserting a new row with status = active. 
     * 
     * @param array $batch
     * 
     * Array with one or more elements. Each element should be another array
     * with the following elements:     
     * network_id, datetime, owner_id, root_id, parent_id, anno_id, anno_text, anno_type
     * 
     * @return
     * 
     * integer 1 upon success
     */
    protected function batchUpdateAnno($batch) {

        $n = count($batch);
        if ($n == 0) {
            return;
        }
        if ($n > 999000) {
            throw new Exception("Too many annotations at once");
        }

        // loop through the batch and make sure each entry has all the required fields
        for ($i = 0; $i < $n; $i++) {
            $batch[$i] = $this->subsetArray($batch[$i], ["network_id", "datetime", "owner_id",
                "root_id", "parent_id", "anno_id", "anno_text", "anno_type"]);
            $batch[$i]['user_id'] = $this->_uid;
        }

        $tat = "" . NC_TABLE_ANNOTEXT;

        // create query to set all existing versions of these annotations as old
        $sql = "UPDATE $tat SET anno_status = " . NC_OLD
                . " WHERE anno_status = " . NC_ACTIVE . " AND (";
        $sqlupdate = [];
        $params = [];
        for ($i = 0; $i < $n; $i++) {
            $x = sprintf("%'.06d", $i);
            $sqlupdate[] = " anno_id = :id_$x ";
            $params["id_$x"] = $batch[$i]['anno_id'];
        }
        $sql .= implode(" OR ", $sqlupdate) . " )";
        $this->qPE($sql, $params);
        unset($sqlupdate, $params);

        // next, create query to insert new copies of these annotations
        $sql = "INSERT INTO $tat "
                . " (datetime, modified, network_id, owner_id, user_id, root_id, parent_id,"
                . " anno_id, anno_text, anno_type, anno_status) VALUES ";
        $sqlinsert = [];
        $params = [];
        for ($i = 0; $i < $n; $i++) {
            $x = sprintf("%'.06d", $i);
            $sqlinsert[$i] = "(:datetime_$x, UTC_TIMESTAMP(), :network_id_$x, "
                    . ":owner_id_$x, :user_id_$x, :root_id_$x, :parent_id_$x, :anno_id_$x, "
                    . ":anno_text_$x, :anno_type_$x, " . NC_ACTIVE . ")";
            foreach ($this->longkeys($batch[$i], "_$x") as $key => $val) {
                $params[$key] = $val;
            }
        }
        $sql .= implode(", ", $sqlinsert);
        $this->qPE($sql, $params);
    }

    /**
     * This works as a layer before batchUpdateAnno. Given data on "new" annotations,
     * this function compares these annotations to the ones existing in the db. The
     * annotations that are different are passed on to batchUpdateAnno.
     * 
     * 
     * @param array $batch
     * 
     * Each element of the array should be another array with elements
     * name, title, abstract, content
     * 
     * The elements in $batch should have keys that correspond to root_id.
     * 
     * @return int
     * 
     * number of items actually updated
     */
    protected function batchCheckUpdateAnno($netid, $batch, $batchsize = 100000) {

        $n = count($batch);
        if ($n == 0) {
            return;
        }
        if ($n > $batchsize) {
            throw new Exception("too many annotations");
        }

        // fetch current annotations from the db
        $sql = "SELECT network_id, datetime, owner_id, root_id, parent_id, "
                . "anno_id, anno_type, anno_text FROM " . NC_TABLE_ANNOTEXT
                . " WHERE network_id = :netid AND anno_type <= " . NC_CONTENT
                . " AND anno_status=" . NC_ACTIVE . " AND (";
        $sqlcheck = [];
        $params = ['netid' => $netid];
        $batchkeys = array_keys($batch);
        for ($i = 0; $i < $n; $i++) {
            $x = sprintf("%'.06d", $i);
            $sqlcheck[] = " root_id = :root_$x ";
            $params["root_$x"] = $batchkeys[$i];
        }
        $sql .= implode(" OR ", $sqlcheck) . ")";
        $stmt = $this->qPE($sql, $params);

        // scan throught the reported annotations and directly compare with the 
        // "new" data in the batch set. Discrepant entries are moved to $toupdate
        $toupdate = [];
        while ($row = $stmt->fetch()) {
            //echo ".";
            $nowtype = array_search($row['anno_type'], $this->_annotypes);
            $nowroot = $row['root_id'];
            if ($batch[$nowroot][$nowtype] != '') {
                if ($batch[$nowroot][$nowtype] != $row['anno_text']) {
                    $pp = $row;
                    $pp['anno_text'] = $batch[$nowroot][$nowtype];
                    $pp['user_id'] = $this->_uid;
                    $toupdate[] = $pp;
                }
            }
        }

        // pass on the shortlisted items, i.e. perform the db updates
        $this->batchUpdateAnno($toupdate);

        // return the number of updated items
        return count($toupdate);
    }

    /**
     * looks up the permission code for a user on a network (given an id)
     *
     * This function takes network and uid separately from the class _network
     * and _uid. This allows, for example, the admin user to get the 
     * permission for the guest user. 
     * 
     * @param string $netid
     * 
     * network id code
     * 
     * @param string $uid
     * 
     * user id 
     * 
     * @throws Exception
     */
    protected function getUserPermissions($netid, $uid) {
        $sql = "SELECT permissions FROM " . NC_TABLE_PERMISSIONS .
                " WHERE BINARY user_id = ? AND network_id = ?";
        $stmt = $this->qPE($sql, [$uid, $netid]);
        $result = $stmt->fetch();
        if (!$result) {
            return NC_PERM_NONE;
        }
        return (int) $result['permissions'];
    }

    /**
     * get a network id associated with a network name
     * 
     * This requires a lookup for the current name in the annotations table.
     * 
     * @param string $netname
     * 
     * A network name like 

      "my- network"
     * 
     * @param logical $throw
     * 
     * set true to automatically throw an exception if the network does not exist
     * 
     * @return string
     * 
     * A code used in the db, e.g. "Wxxxxxx".
     * If a network does nto exists, return the empty string.
     *  
     */
    protected function getNetworkId($netname, $throw = false) {
        $sql = "SELECT network_id FROM " . NC_TABLE_ANNOTEXT . "
                    WHERE BINARY anno_text = ? AND anno_type = " . NC_NAME . "
                    AND anno_status = 1";
        $stmt = $this->qPE($sql, [$netname]);
        $result = $stmt->fetch();
        if (!$result) {
            if ($throw) {
                throw new Exception("Network does not exist (getNetworkId) $netname");
            }
            return "";
        } else {
            return $result['network_id'];
        }
    }

    /**
     * Get the root id associated with a given name-level annotation.
     * E.g. if we know a class name is "GOOD_NODE

      ", this function will
     * return the "Cxxxxxx " code associated with this class name.
     * 
     * 
     * @param type $netname
     * @param type $nameanno
     * 
     * @return string 
     * 
     * The root id, or empty string when the name annotation does not match
     */
    protected function getNameAnnoRootId($netid, $nameanno, $throw = true) {

        $sql = "SELECT root_id, anno_status FROM " . NC_TABLE_ANNOTEXT . "
                    WHERE BINARY network_id = ? AND anno_text = ? AND
                    anno_type = " . NC_NAME . " AND anno_status != " . NC_OLD;
        $stmt = $this->qPE($sql, [$netid, $nameanno]);
        $result = $stmt->fetch();
        if ($throw) {
            if (!$result) {
                throw new Exception("Name '$nameanno' does not match any annotations");
            }
        }
        return $result;
    }

    /**
     * Get a full set of text annotations (name, title, etc) for a given root id.
     * e.g. use this to fetch summary information about a given network (rootid="Wxxxx")
     * or a link (rootid="Lxxxxxx")
     * 
     * @param string $netid
     * @param string $rootid
     * @param boolean $throw
     * 
     * set true to throw an exception if the rootid is not found
     * 
     * @return array
     * 
     * output will be an array, each element of which will contain a further array.
     * The second level array will have full information about that annotation. 
     * 
     * @throws Exception
     */
    protected function getFullSummaryFromRootId($netid, $rootid, $throw = true) {

        // fetch all the summary annotations for a given root (without pivoting)                        
        $sql = "SELECT network_id, datetime, owner_id, root_id, parent_id, 
            anno_id, anno_type, anno_text FROM " . NC_TABLE_ANNOTEXT . "  
                WHERE network_id = ? AND root_id = ?                  
                  AND anno_type <= " . NC_DEFS . " AND anno_status=" . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$netid, $rootid]);
        $result = [];
        while ($row = $stmt->fetch()) {
            switch ($row['anno_type']) {
                case NC_NAME:
                    $result['name'] = $row;
                    break;
                case NC_TITLE:
                    $result['title'] = $row;
                    break;
                case NC_ABSTRACT:
                    $result['abstract'] = $row;
                    break;
                case NC_CONTENT:
                    $result['content'] = $row;
                    break;
                case NC_DEFS:
                    $result['defs'] = $row;
                    break;                
                default:
                    break;
            }
        }
        if (count($result) == 0) {
            if ($throw) {
                throw new Exception("Annotations for '$rootid' do not exist");
            }
        }
        return $result;
    }

    /**
     * Fetches the anno_id associated with an annotation
     * (e.g. if want to update the title of a network,
     * set netid, rootid=netid, level=NC_NAME
     * 
     * @param type $netid
     * @param type $rootid
     * @param type $level
     * @return string
     * 
     * the anno_id
     * 
     * @throws Exception
     * 
     * (should never happen as this function is used internaly)
     * 
     */
    protected function getAnnoId($netid, $rootid, $level) {
        if ($level != NC_ABSTRACT && $level == NC_TITLE && $level == NC_CONTENT) {
            throw new Exception("Function only suitable for title, abstract, content");
        }
        $sql = "SELECT anno_id FROM " . NC_TABLE_ANNOTEXT . "
                    WHERE BINARY network_id = ? AND root_id = ? AND
                    anno_type = ? AND anno_status = " . NC_ACTIVE;
        $stmt = $this->qPE($sql, [$netid, $rootid, $level]);
        $result = $stmt->fetch();
        if (!$result) {
            throw new Exception("Error fetching anno id");
        }
        return $result['anno_id'];
    }

    /**
     * New way of inserting a set of annotation that does not use anno_id lookup
     * and uses only on insert statement.
     * 
     * @param type $netid
     * @param type 

      $uid
     * @param type  $rootid
     * @param type $annoname
     * @param type $annotitle
     * @param type $annoabstract
     * @param type $annocontent
     * 
     */
    protected function insertNewAnnoSet($netid, $uid, $rootid, $annoname, $annotitle, $annoabstract = 'empty', $annocontent = 'empty') {

        throw new Exception("insertNewAnnoSet is deprecated. Use batchInsertAnnoSets instead");

        $annoid = $this->makeRandomID(NC_TABLE_ANNOTEXT, "anno_id", "T", NC_ID_LEN - 1);

        // create an array of parameter values (one set for name, abstrat, title, content)
        $params = [];
        foreach (["A", "B", "C", "D"] as $abc) {
            $params['network_id' . $abc
                    ] = $netid;
            $params['owner_id' . $abc
                    ] = $uid;
            $params['user_id' . $abc] = $uid;
            $params['root_id' . $abc] = $rootid;
            $params['parent_id' . $abc] = $rootid;
        }
        $params['anno_idA'] = $annoid . NC_NAME;
        $params['anno_idB'] = $annoid . NC_TITLE;
        $params['anno_idC'] = $annoid . NC_ABSTRACT;
        $params['anno_idD'] = $annoid . NC_CONTENT;
        $params['anno_textA'] = $annoname;
        $params['anno_textB'] = $annotitle;
        $params['anno_textC'] = $annoabstract;
        $params['anno_textD'] = $annocontent;

        $sql = "INSERT INTO " . NC_TABLE_ANNOTEXT . "
                    (datetime, network_id, owner_id, user_id, root_id, parent_id,
                    anno_id, anno_text, anno_type, anno_status) VALUES
                    (UTC_TIMESTAMP(), :network_idA, :owner_idA, :user_idA, :root_idA, :parent_idA,
                    :anno_idA, :anno_textA, " . NC_NAME . ", " . NC_ACTIVE . " ),
                    (UTC_TIMESTAMP(), :network_idB, :owner_idB, :user_idB, :root_idB, :parent_idB,
                    :anno_idB, :anno_textB, " . NC_TITLE . ", " . NC_ACTIVE . " ),
                    (UTC_TIMESTAMP(), :network_idC, :owner_idC, :user_idC, :root_idC, :parent_idC,
                    :anno_idC, :anno_textC, " . NC_ABSTRACT . ", " . NC_ACTIVE . " ),
                    (UTC_TIMESTAMP(), :network_idD, :owner_idD, :user_idD, :root_idD, :parent_idD,
                    :anno_idD, :anno_textD, " . NC_CONTENT . ", " . NC_ACTIVE . " ) ";

        $this->qPE($sql, $params);
    }

    /**
     * @param string $netid
     * 
     * network id code
     * 
     * @param array $annosets
     * 
     * a 2d array. The first index should be accessible by an integer interator. 
     * The elements accessed by the first index should be associative arrays with key:
     * 
     * 'name', 'title', 'abstract', 'content',
     * 
     * @param array $rootids
     * 
     * a 1d array that match the $annosets.
     */
    protected function batchInsertAnnoSets($netid, $annosets, $rootids) {

        //echo "bIAS 1 ";
        $n = count($annosets);
        if ($n > 999000) {
            throw new Exception("Too many annotations at once");
        }
        //echo "bIAS 2 ";
        $annoids = $this->makeRandomIDSet(NC_TABLE_ANNOTEXT, "anno_id", "T", NC_ID_LEN - 1, $n);
        //echo "bIAS 3 ";
        // start making the query
        $sql = "INSERT INTO " . NC_TABLE_ANNOTEXT . "
                    (datetime, network_id, owner_id, user_id, root_id, parent_id,
                    anno_id, anno_text, anno_type, anno_status) VALUES ";

        // prepare the secong part of the query 
        // this involves making an array of (..., ..., ...) sets and matching params
        // later this info will be concatenated and executed
        $sqlinsert = [];
        $params = [];
        $uid = $this->_uid;
        for ($i = 0; $i < $n; $i++) {
            // create a numeric string that will not be confused e.g. x1 and x12 both match x1.
            $x = sprintf("%'.06d", $i);
            // some shorthand to encode data that does not have to be prepped
            $ri = $rootids[$i];
            foreach ($this->_annotypeslong as $key => $val) {
                if (array_key_exists($key, $annosets[$i])) {
                    $nowid = $annoids[$i] . $val;
                    $nowtext = $annosets[$i][$key];
                    if (strlen($nowtext) < 2) {
                        $nowtext = $annosets[$i]['name'];
                    }
                    // create an entry with value sets for the sql statement
                    $sqlinsert[] = "(UTC_TIMESTAMP(), '$netid', '$uid', '$uid', '$ri', '$ri',
                    '$nowid' , :anno_text_$val$x, $val, " . NC_ACTIVE . " )";
                    $params["anno_text_$val$x"] = $nowtext;
                }
            }
        }

        //print_r($params);
        //echo "bIAS 5 ";
        $sql .= implode(", ", $sqlinsert);
        //echo "bIAS 6 \n$sql \n";
        $this->qPE($sql, $params);
        //echo "bIAS 7 ";
    }

    /**
     * Get a small array using only a few elements from a larger (assoc) array
     * 
     * @param array $array
     * @param array $keys


     * @return array
     * 
     */
    protected function subsetArray($array, $keys) {

        // perform the subset
        $result = array_intersect_key($array, array_flip($keys));

        // check if all the keys were found
        if (count($result) !== count($keys)) {
            // some of the keys are missing, make a string with a summary
            $missing = "";
            for ($i = 0; $i < count($keys); $i++) {
                if (!array_key_exists($keys[$i], $array)) {
                    $missing .= " " . $keys[$i];
                }
            }
            throw new Exception("Missing keys: $missing");
        }

        return $result;
    }

    /**
     * creates a new array with longer keys
     * 
     * e.g. can use this change a keys like "net" to "net_001"
     * 
     * @param array $array
     * 
     * associative array
     * 
     * @param type $suffix
     * 
     * a suffix to append to all keys
     * 
     * @return array
     * 
     * array of same size as before, but with different keys
     * 
     */
    protected function longkeys($array, $suffix) {
        $result = [];
        foreach ($array as $key => $value) {
            $result[$key . $suffix] = $value;
        }
        return $result;
    }

    private function annocode2string($x) {
        switch ($x) {
            case NC_NAME:
                $nowtype = "name";
                break;
            case NC_TITLE:
                $nowtype = "title";
                break;
            case NC_ABSTRACT:
                $nowtype = "abstract";
                break;
            case NC_CONTENT:
                $nowtype = "content";
                break;
            default:
                break;
        }
    }

}

?>
