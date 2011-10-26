<?php
include 'helpers/config.php';

use ActiveRecord\Config;
use ActiveRecord\ConnectionManager;

class ConnectionManagerTest extends DatabaseTest
{
	public function testGetConnectionWithNullConnection()
	{
		$this->assertNotNull(ConnectionManager::getConnection(null));
		$this->assertNotNull(ConnectionManager::getConnection());
	}
    
	public function testGetConnection()
	{
		$this->assertNotNull(ConnectionManager::getConnection('mysql'));
	}

	public function testGetConnectionUsesExistingObject()
	{
		$a = ConnectionManager::getConnection('mysql');
		$a->harro = 'harro there';

		$this->assertSame($a,ConnectionManager::getConnection('mysql'));
	}

    public function testGh91GetConnectionWithNullConnectionIsAlwaysDefault()
    {
        $connOne = ConnectionManager::getConnection('mysql');
        $connTwo = ConnectionManager::getConnection();
        $connThree = ConnectionManager::getConnection('mysql');
        $connFour = ConnectionManager::getConnection();

        $this->assertSame($connOne, $connThree);
        $this->assertSame($connTwo, $connThree);
        $this->assertSame($connFour, $connThree);
    }
}
?>
