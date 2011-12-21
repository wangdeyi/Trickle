<?php
/**
 * @author kim (happy life, happy PHP, lol)
 * @since 2011-11-28
 * @version 1.0.0
 */

/**
 * class MongoAdapter
 *
 * Simple Mongo operation wrapper
 *
 * @example
 * $config = array(
 *           'servers'  => 'localhost:27017',
 *           'database' => 'test',
 *           'username' => 'kim',
 *           'password' => 'kim'
 * );
 * $mongo = new MongoAdapter($config);
 * $commentList = $mongo->findAll('comment');
 *
 * @tutorial
 * it's recommended to use the adapter together with your model classes:
 * $mongo = new MongoAdapter($config);
 * $comment = new Comment($mongo);
 *
 * @todo
 * # MongoCollection:: remove() - delete function, should be impletment soon!!
 * 
 * # MongoCollection::batchInsert() - insert multiple records at one time.
 * # MongoDB::listCollections() - throw some exceptions if collection unexists?
 * # MongoDB::setProfilingLevel() - for profiling under development/testing?
 * # MongoDB::command - sending command to mongodb?
 * # MongoCollection::ensureIndex - enable user to adding indexes?
 * # MongoDB::execute - interface of executing javascript functions?
 * # Should MongoPool::setSize() - to limit the pool size? 
 * # MongoGridFS - for file upload cases?
 * # MongoTimestamp - and auto-sharding?
 * # MongoMinKey / MongoMaxKey - let some records be always popular on top?
 * 
 */
final class MongoAdapter
{
    /**
     * connection object
     * 
     * @link http://www.php.net/manual/en/class.mongo.php
     * @var Mongo - instance of Mongo
     */
    protected $_connection = null;

    /**
     * db object
     *
     * @link http://www.php.net/manual/en/class.mongodb.php
     * @var MongoDB - instance of MongoDB
     */
    protected $_db = null;

    /**
     * database configurations
     * 
     * @var array $_config
     */
    protected $_config = array(
        'servers'     => array('127.0.0.1:27017'),
        'database'    => null,
        'username'    => null,
        'password'    => null,
    );

    /**
     * Mongo options
     * @link http://www.php.net/manual/en/mongo.connecting.php
     * 
     * "Persistent connections are highly recommended and should always
     *  be used in production unless there is a compelling reason not to."
     * 
     * "If you are using a replica set...
     *  the driver can automatically route reads to slaves."
     * 
     * @var array $_options
     */
    protected $_options = array(
        'persist' => 'x',
        'replicaSet' => false,
    );

    /**
     * @return array $_options
     */
    public function getOptions ()
    {
        return $this->_options;
    }

    /**
     * @param array $_options
     */
    public function setOptions ($_options)
    {
        $this->_options = $_options;
    }

    /**
     * @return Mongo
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    /**
     * @param Mongo $connection
     */
    public function setConnection($connection)
    {
        $this->_connection = $connection;
    }

    /**
     * @return MongoDB
     */
    public function getDb()
    {
        return $this->_db;
    }

    /**
     * @param MongoDB $db
     */
    public function setDb($db)
    {
        $this->_db = $db;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->_config;
    }

    /**
     * @param array $config
     */
    public function setConfig($config)
    {
        $this->_config = $config;
    }

    /**
     * construct
     * only set configurations
     * should not do any connectting until real querying
     *
     * @param array $config
     * @param mixed $options
     * @return void
     */
    public function __construct($config, $options = null)
    {
        // set mongo options
        if (is_array($options) and !empty($options)) {
            $this->setOptions($options);
        }

        // set mongo connection configs
        if (is_array($config) and !empty($config)) {
            $this->setConfig($config);
        } else {
            throw new MongoException("Pleae provide connection configs!");
        }
    }

    /**
     * do connect store the Mongo instance
     * then select the database and store MongoDB instance
     * just before the first query
     * 
     * @link http://www.php.net/manual/en/mongo.connecting.php
     * we are new using the URI format
     * because it will auto reauthenticate after reconnect 
     * 
     * @return MongoDB - return Mongo database object
     */
    protected function _connect()
    {
        if (!$this->_db) {
            $config = $this->getConfig();
            if (!empty($config) and is_array($config)) {

                // init connection informations, using URI format
                $connectInfo = 'mongodb://';

                /**
                 * auth information
                 * @todo what if a username got ':' at the end?
                 * @todo what if a password got '@' at the end?
                 */
                if (isset($config['username']) && isset($config['password'])) {
                    $connectInfo .= (string)$config['username'] . ':'
                                  . (string)$config['password'] . '@';
                }

                // server informations
                if (!empty($config['servers'])) {
                    if (is_array($config['servers'])) {
                        $connectInfo .= implode(',', $config['servers']);
                    } else {
                        $connectInfo .= (string)$config['servers'];
                    }

                    // is multiple servers then it should be replica set
                    if (count($config['servers']) > 1) {
                        $options = $this->getOptions();
                        $options['replicaSet'] = true;
                        $this->setOptions($options);                        
                    }
                } else {
                    throw new MongoException("Server configs missing!");
                }

                // database name
                if (!empty($config['database'])) {
                    $dbname = (string)$config['database'];
                    $connectInfo .= '/' . $dbname;
                } else {
                    throw new MongoException("Database configs not found!");
                }

                /**
                 * get connection
                 * @todo do logs?
                 */
                try {
                    $options = $this->getOptions();
                    $conn = new Mongo($connectInfo, $options);
                    $this->setConnection($conn);
                    
                    // for testing replica sets
                    //echo 'Debug: <br />'; var_dump($conn->getHosts());

                } catch (MongoConnectionException $e) {
                    throw new MongoConnectionException(
                            "Fails to connect : " . $e->getMessage());
                }
                
                /**
                 * get database and try to ping
                 * 
                 * by the time when this package is under development,
                 * the small funny "bug" is still not fixed by the author
                 * @link https://bugs.php.net/bug.php?id=60508
                 */
                $db = $conn->selectDB($dbname);
                if (!$db or !($db instanceof MongoDB)) {
                    throw new MongoException("Unable to select database!");
                }
                try {
                    $db->command(array("ping" => 1));
                } catch (MongoCursorException $e) {
                    throw new MongoConnectionException(
                            "Fails to connect db : " . $e->getMessage());
                }

                // pass queries to slaves by default
                $db->setSlaveOkay(true);
                $this->setDb($db);
            } else {
                throw new MongoException("Invalid configurations!");            
            }
        }

        return $this->getDb();
    }

    /**
     * get collection object
     * 
     * @param string $collectionName
     * @return MongoCollection
     */
    protected function _getCollection($collectionName)
    {
        $db = $this->_connect();
        if (!empty($collectionName)) {
            $collection = $db->selectCollection($collectionName);
            if ($collection instanceof MongoCollection) {
                return $collection;
            } else {
                throw new MongoException("Unknow collection!");                
            }
        } else {
            throw new MongoException("Collection name missing!");
        }
    }

    /**
     * Disconnect : if you are connected to a replica set, 
     * close() will only close the connection to the primary.
     *
     * @return void
     */
    protected function _disconnect()
    {
        $conn = $this->getConnection();
        if ($conn instanceof Mongo) {
            $conn->close();
        }
    }

    /**
     * do insert
     * 
     * @link http://www.php.net/manual/en/mongo.writes.php
     * "To get a response from the database, use the safe option, 
     *  available for all types of writes. This option will make sure that
     *  the database has the write before returning success."
     * 
     * @param string $collectionName
     * @param array $data
     * @param boolean $nbSafeInsert - number of slaves that should get the copy 
     * @return boolean
     */
    public function insert($collectionName, $data, $nbSafeInsert = 1)
    {
        $collection = $this->_getCollection($collectionName);
        $result = $collection->insert($data, array('safe' => $nbSafeInsert));
        if (isset($result['ok']) and $result['ok'] == 1) {
            return true;
        }
        return false;
    }

    /**
     * do update, could be multiple
     * 
     * @param string $collectionName
     * @param array $conditions
     * @param array $newobj
     * @param array $options
     * @return boolean - If safe was set, returns an array containing the 
     *                   status of the update. Otherwise, returns a boolean 
     *                   representing if the array was not empty
     */
    public function update($collectionName, $conditions,
                           $newobj, $options = array())
    {
        $collection = $this->_getCollection($collectionName);
        $criteria = $this->_buildCriteria($conditions);
        $result = $collection->update($criteria, $newobj, $options);
        return $result;
    }

    /**
     * do save, for only one document
     * 
     * @param string $collectionName
     * @param array $conditions
     * @param array $fields - the fields and values to save
     * @return array|bool
     */
    public function save($collectionName, $conditions, $fields)
    {
        $collection = $this->_getCollection($collectionName);
        $criteria = $this->_buildCriteria($conditions);
        $row = $collection->findOne($criteria);
        if ($row) {
            foreach ($fields as $field => $value) {
                $row[$field] = $value;
            }
        }
        return $collection->save($row);
    }

    /**
     * find one record
     * 
     * @param string $collectionName
     * @param array $conditions
     * @param array $fields
     * @return MongoCursor
     */
    public function findOne($collectionName, $conditions, $fields = array())
    {
        $result = null;
        $collection = $this->_getCollection($collectionName);
        $criteria = $this->_buildCriteria($conditions);
        if ($row = $collection->findOne($criteria, $fields)) {
            $result = $row;
        }
        return $result;
    }

    /**
     * find all the records
     * 
     * @todo
     * MongoCursor::addOption()
     * MongoCollection::group()
     * MongoCursor::limit() / MongoCursor::skip()
     * 
     * @param string $collectionName
     * @param array $query - the queries for find()
     * @param array $operations
     * @return MongoCursor
     */
    public function findAll($collectionName, $query = null, $operations = null)
    {
        $collection = $this->_getCollection($collectionName);
        $entities = $collection->find($query);

        // if some operations need to apply
        if (is_array($operations) and !empty($operations)) {
            if (isset($operations['skip'])) {
                $entities = $entities->skip((int)$operations['skip']);
            }
            if (isset($operations['limit'])) {
                $entities = $entities->limit((int)$operations['limit']);
            }
            if (isset($operations['sort']) and is_array($operations['sort'])) {
                $entities = $entities->sort($operations['sort']);
            }
        }
        return $entities;
    }

    /**
     * count number of collections
     *
     * @param string $collectionName
     * @return integer
     */
    public function count($collectionName, $query = array(),
                          $limit = 0, $skip = 0)
    {
        $collection = $this->_getCollection($collectionName);
        $result = $collection->count($query, $limit, $skip);
        return $result;
    }

    /**
     * get reference document
     * 
     * @param string $collectionName
     * @param array $ref - the reference object
     * @return MongoCursor
     */
    public function getDbRef($collectionName, $ref)
    {
        $result = array();
        if (!empty($ref) and MongoDBRef::isRef($ref)) {
            $collection = $this->_getCollection($collectionName);
            $result = $collection->getDBRef($ref);
        }
        return $result;
    }

    /**
     * create reference document by id
     * we now transform the string id (which is invalid for reference creation)
     * to MongoId by default
     *
     * @param string $collectionName - the collection name
     * @param string|MongoId $objId - the document id
     * @return array
     */
    public function createRef($collectionName, $objId)
    {
        if (!empty($objId)) {
            if (!($objId instanceof MongoId)) {
                $objId = new MongoId($objId);
            }
            $collection = $this->_getCollection($collectionName);
            $ref = $collection->createDBRef($objId);
            return $ref;
        } else {
            throw new MongoException("Invalid object id!");
        }
    }

    /**
     * drop the collection, example of response:
     * 
     * success:
     * array(4) { ["nIndexesWas"]=> float(1)
     *            ["msg"]=> string(30) "indexes dropped for collection" 
     *            ["ns"]=> string(12) "test.comment"
     *            ["ok"]=> float(1) }
     *            
     * fails:            
     * array(2) { ["errmsg"]=> string(12) "ns not found"
     *            ["ok"]=> float(0) }
     * 
     * @param string $collectionName
     * @return true
     */
    public function drop($collectionName)
    {
        $collection = $this->_getCollection($collectionName);
        $result = $collection->drop();
        if (isset($result['ok']) and !$result['ok']) {
            throw new MongoException(
                    "Unable to drop collection : " . $collectionName);
        }
        return true;
    }

    /**
     * build criterias by conditions array provided
     * 
     * @todo other conditions...
     * 
     * @param array $conditions
     * @return array $criteria - queries in mongo format
     */
    protected function _buildCriteria($conditions)
    {
        $criteria = array();
        if (!empty($conditions) and is_array($conditions)) {
            foreach ($conditions as $key => $value) {
                switch ($key) {
                    case '_id':
                        if (!($value instanceof MongoId)) {
                            $criteria['_id'] = new MongoId($value);
                        }
                        break;
                }
            }
        }
        return $criteria;
    }

    /**
     * free the connection
     *
     * @return void
     */
    public function free()
    {
        $this->_disconnect();
        $this->_db = null;
        $this->_connection = null;
    }

} 

