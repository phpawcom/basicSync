basicSync
=========

Basic Synchronization Script is just a single file to synchronize the data from a branch to main database.

##### How to use the script
- First Backup your databases
- Update branches databases with the Database.update.sql
- Add 'tables to synchronize' in synchronization table and make sure to write an existing table name and its primary key
- Configure the connection Details:
```
$db1 = new db('Server', 'Username', 'Password', 'Database Name', 'Prefix'); // Main Database
$db2 = new db('Server', 'Username', 'Password', 'Database Name', 'Prefix');  // Another Database
$sync = new sync($db1, $db2); 
```

##### Notes
- The script does not check for duplicated records
- There should be just 1 main database
- This is  a beta version