Live on: https://orderist.smdmitry.com  

The objective was to make very simple analog of [AirTasker](https://www.airtasker.com), [YouDo](https://youdo.ru) 

##### Acknowledgements:  
1) Commission of the website is 10%.  
2) Money is handled as integers (cents), amount can't be less than 1 cent.  
3) To create order user must have enough balance. Order price is frozen untill it's completed or deleted. This ensures that user balance will never become negative.  
4) Commission of the website is rounded up. (Example: 100.00005 USD = 100.01 USD)  
5) Commission can be greater than 10%, if there is not enough accuracy. (Example: order price is 0.02 USD, then commission is 0.01 USD = 50%)  

##### Implementation:
1) Phalcon Framework, Memcache, NodeJS (socket.io), Bootstrap, JQuery. 
2) All tables can be on seperate DB servers (payments table is sharded by user_id)  
3) Database transactions are not used at all (tables are InnoDB though)  
4) Can handle highload, all db queries are indexed, and data cached by Memcache.  
5) Websocket is used to notify about balance changes, orders statues, and first 1000 orders on mainpage.
6) Order status changes are made without locks, consistency achieved by conditions in MySQL queries.  
7) Balance changes are made through lockind.  

##### Fault-tolerance:
1) Data consistency achieved by locks on memcache_add.  
2) There will be no data corruption if any part of the system will fail at any line of code.  
3) To speed up fixing data consistency after failure there are additional fields in database. 
There is code which [detects and fixes them](https://github.com/smdmitry/orderist/blob/master/apps/controllers/AdminController.php#L53)  

##### Notes:
1) There is no AJAX navigation, but all actions are AJAX  
2) No user sessions are used, authentification is done by hash of user_id,password,email  
3) Websockets and email sending are not ready for production

##### How to test: 

You can modify your balance on [balance page](https://orderist.smdmitry.com/user/cash/).  

There is [admin panel](https://orderist.smdmitry.com/admin/), to enable/disable debug and clear memcache.  
Database admin panel to see [tables and data](https://orderist.smdmitry.com/adminer.php). (Login/Password: readonly)  

When debug is on [FireLogger](https://firelogger.binaryage.com/) ([Firefox](https://addons.mozilla.org/ru/firefox/addon/firelogger/), [Chrome](https://smd.im/WOs)) will display all queries to MySQL and Memcache. [Example](https://scr.smd.im/fs-ju4r5fnec5-2016-03-22-11_35_13.png)  

FireLogger sometimes pushes too much data in headers and nginx does not handle this correctly. So if there is 502 error, than just disable Debug.
