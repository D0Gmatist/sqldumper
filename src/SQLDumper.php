<?php
namespace Y0lk\SQLDumper;

use PDO;

/**
 * SQLDumper is used to create a complete dump from a SQL database
 *
 * @author Gabriel Jean <gabriel@inkrebit.com>
 */
class SQLDumper
{
    /**
     * @var string  Host for the DB connection
     */
    protected $host = '';

    /**
     * @var string  Name of the DB
     */
    protected $dbname = '';

    /**
     * @var string  Username used for the DB connection
     */
    protected $username = '';

    /**
     * @var string  Password used for the DB connection
     */
    protected $password = '';

    /**
     * @var PDO PDO instance of the DB
     */
    protected $db = NULL;

    /**
     * @var TableDumperCollection   Contains all TableDumper objects that will be used for this dump
     */
    protected $listTableDumpers;


    /**
     * @param string    Host for the DB connection
     * @param string    Name of the DB
     * @param string    Username used for the DB connection
     * @param string    Password used for the DB connection
     */
    public function __construct($host, $dbname, $username, $password = '') 
    {
        $this->host = $host;
        $this->dbname = $dbname;
        $this->username = $username;
        $this->password = $password;

        //Init main TableDumperCollection
        $this->listTableDumpers = new TableDumperCollection;
        $this->connect();
    }

    /**
     * Connects to the SQL database
     * 
     * @return void
     */
    protected function connect() 
    {
        $this->db = new PDO('mysql:host='.$this->host.';dbname='.$this->dbname, 
                            $this->username, 
                            $this->password,
                            [
                                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                            ]
        );
    }

    /**
     * Get the main list of TableDumper
     * 
     * @return TableDumperCollection Returns the list of table dumpers as TableDumperCollection
     */
    public function getListTableDumpers()
    {
        return $this->listTableDumpers;
    }

    /**
     * Set a TableDumper for the given table in order to specify certain dump options on it
     * 
     * @param  Table|string The table to set
     * 
     * @return TableDumper  Returns a TableDumper
     */
    public function table($table)
    {
        return $this->listTableDumpers->addTable($table);
    }

    /**
     * Set a TableDumperCollection for the given list of tables in order to specify certain dump options on them
     * 
     * @param  TableDumperCollection|array<TableDumper|Table|string>    The list of tables to set (either as a TableDumperCollection, or an array containing either TableDumper objects, Table objects or table names) 
     * 
     * @return TableDumperCollection    Returns a TableDumperCollection
     */
    public function listTables($listTables)
    {   
        return $this->listTableDumpers->addListTables($listTables);
    }

    /**
     * Set a TableDumperCollection for all the tables in the database, in order to specify certain dump options on them
     * 
     * @return TableDumperCollection    Returns a TableDumperCollection
     */
    public function allTables()
    {
        //Fetch all table names
        $stmt = $this->db->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema=:dbname');
        $stmt->bindValue(':dbname', $this->dbname, PDO::PARAM_STR);
        $stmt->execute();

        $listRows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stmt->closeCursor();

        //Call list tables with returned table names
        $listDumpers = $this->listTables($listRows);

        return $listDumpers;
    }

    /**
     * Writes the complete dump of the database to the given stream
     * 
     * @param  resource Stream to write to
     * 
     * @return void
     */
    public function dump($stream)
    {
        //TODO: Find a way to not use that an instead execute all foreign key constraints at the end
        fwrite($stream, "SET FOREIGN_KEY_CHECKS=0;\r\n");

        foreach ($this->listTableDumpers as $tableDumper) {
            $tableDumper->dump($this->db, $stream);
        }

        fwrite($stream, "SET FOREIGN_KEY_CHECKS=1;\r\n");
    }

    /**
     * Creates and saves the dump to a file
     * 
     * @param  string   File name for the dump
     * 
     * @return void
     */
    public function save($filename) 
    {
        $stream = fopen($filename, 'w');
        $this->dump($stream);
        fclose($stream);
    }
}