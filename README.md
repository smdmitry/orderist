http://orderist.smdmitry.com

##### Приняты следующие допущения:
1) Комиссия системы составляет 10%.
2) Расчеты и хранение денег осуществляется целочисленно в копейках, меньше 1 копейки быть не может.
3) Для создания заказа у пользователя должна быть достаточная сумма на счету. При создании заказа эта сумма блокируется до его выполнения или удаления (соответственно, свободная сумма баланса становится меньше). Это позволяет гарантировать, что баланс пользователя не уйдет в минус и он не останется нам должен.

При фиксированном проценте комиссии мне не нравились цены (если стоимость заказа круглая, то доход исполнителя получался с копейками и наоборот). Поэтому я решил сделать два режима работы:
1) Расчеты без округления, с точностью до копейки
2) Расчеты с округлением, чтобы получались "красивые" цены, за счет варьирования комиссии сайта (от 8% до 10%)
Эти режимы можно переключить в админке (http://smd.im/aCj), по умолчанию включён режим с округлением, как более дружелюбный к пользователю.

При точных расчетах следующие правила:
1) Комиссия системы округляется в большую сторону. (Например: 100.00005 руб = 100.01 руб)
2) Процент комиссии системы может быть больше 10% (но точно не меньше), если не хватает точности, чтобы взять меньшую комиссию. (Например: стоимость заказа 0.02 руб, тогда комиссия 0.01 руб = 50%)

##### Реализация:
1) Использутся Phalcon Framework, Memcache, NodeJS (socket.io), Bootstrap, JQuery. 
2) PHP код с ООП, но без сильно древовидных структур, чисто для разнесения кода по логическим блокам, поэтому должен превращаться в код без ООП без серьезных сложностей. Надеюсь, что это ок.
3) Все таблички независимые и могут быть на отдельных серверах. (Таблица с платежами payments - пошардена по user_id, остальные нет)
4) Транзакции в БД не используются вообще. (хотя сами таблицы InnoDB)
5) Всё рачитано на нагрузку, данные кешируются в Memcache.
6) Вебсокет используется для оповещения о изменени баланса пользователя, статусов его заказов, а также первой 1000 заказов на главной. (с учетом авторизации пользователя)
7) Изменение заказов сделано без блокировок, атомарность обеспечивается на уровне условий в MySQL.
8) Изменение баланса юзера через блокировку.

##### Отказоустойчивость:
1) Целостность данных обеспечивается блокировками на memcache_add, поэтому если он вообще не работает то пострадает не только скорость, но и может пострадать целостность.
2) Предусмотрена корректная работа при падении в любом месте PHP кода или базы.
3) Для более быстрого и простого восстановления целостности данных в случе отказа в таблицы добавлены дополнительные поля. (Можно обойтись и без них, но тут весь вопрос в том, как часто, и как, и что падает, и как быстро мы хотим всё починить после падения) Есть код, который детектирует проблемы и исправляет: apps/controllers/AdminController.php:31

##### Замечания:
1) AJAX навигацию решил не делать, но все контекстные действия выполняются AJAXом
2) Не используются сессии, авторизация пользователя осуществляется по хэшу от его id,пароля,email
3) Реализация вебсокетов не совсем для продакшена, потому что каждая вкладка устанавливает свое соединение. Надо бы сделать одну вкладку ведущей и отправлять из неё данные на все остальные.
4) В текущей реализации в принципе можно попробовать обойтись и без плокировок, потому что расчетов баланса на стороне PHP нет, а сложение/вычитание осуществляется на уровне БД атомарно. Поэтому, максимум, что может произойти без блокировок - это будет невалидный кеш юзера и уход баланса в минус. Сами данные в БД будут целостными.
5) Таблица с заказами получилась довольно большой и нагруженной. Необходимость оптимизации работы с ней надо учитывать исходя из предполагаемого трафика и частоты добавлений/выполнений заказов. Например,
разделить на несколько: таблицы для выборок списка айдишек заказов и основная, для хранения данных о самом заказе. (описание, состояние и проч.)
6) Отправка данных из PHP в NodeJS тоже не для прода: прямой HTTP запрос, а надо бы через очередь развязать. С отправкой почты тоже самое.

##### Посмотреть/потестить: 
Тут: http://orderist.smdmitry.com

Для тестирования на странице баланса можно пополнить и вывести деньги со счета:
http://orderist.smdmitry.com/user/cash/

Также, есть админка, где можно включить/выключить режим отладки (Debug) и очистить все данные в memcache.
Посмотреть таблицы и данные в БД можно тут: http://orderist.smdmitry.com/adminer.php (Логин/Пароль: readonly)

В режиме отладки в расширении FireLogger (Firefox: http://smd.im/eJ3, Chrome: http://smd.im/WOs) можно будет посмотреть все запросы к MySQL и Memcache, которые были выполнены (работает и для AJAX запросов тоже).
Пример: https://scr.smd.im/fs-ju4r5fnec5-2016-03-22-11_35_13.png
FireLogger иногда пихает слишком много данных в заголовки и nginx захлебывается, я увеличил размер буферов - это должно помочь, но если выдает 502 ошибку, то нужно просто отключить Debug.