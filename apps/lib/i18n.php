<?php

$GLOBALS['i18n_locale'] = 'en';
//setlocale(LC_ALL, 'ru_RU.UTF8');

$translationRu = [
    'Orderist' => 'Заказист',
    'Signup' => 'Регистрация',
    'Password' => 'Пароль',
    'Login' => 'Войти',
    'Password recovery' => 'Восстановление пароля',
    'All orders' => 'Все заказы',
    'My orders' => 'Мои заказы',
    'Create order' => 'Разместить заказ',
    'USD' => 'руб.',
    'Balane' => 'Баланс',
    'Error' => 'Ошибка',
    'Profile' => 'Профиль',
    'Log out' => 'Выход',
    'My profile' => 'Мой профиль',
    'Signup' => 'Регистрация',
    'Error occurred!' => 'Произошла ошибка!',
    'Welcome!' => 'Добро пожаловать!',
    'All available orders' => 'Все доступные заказы',
    'You received' => 'Вы получили',
    'You will receive' => 'Вы получите',
    'Take order' => 'Выполнить заказ',
    'Order deleted' => 'Заказ удалён',
    'Order completed' => 'Заказ выполнен',
    'Delete order' => 'Удалить заказ',
    'Client:' => 'Заказчик:',
    'Executer:' => 'Выполнил:',
    'Your proifle' => 'Ваш профиль',
    'First name' => 'Имя',
    'Your balance:' => 'Ваш баланс:',
    'locked' => 'заблокировано',
    'and' => 'ещё',
    'this is for testing only' => 'сделано только для удобства тестирования',
    'Nullify' => 'Обнулить',
    'Withdraw 100 USD' => 'Вывести со счета 100 руб.',
    'Topup 100 USD' => 'Пополнить счет на 100 руб.',
    'Transactions history' => 'История транзакций',
    'Date' => 'Дата',
    'Amount' => 'Сумма',
    'Description' => 'Описание',
    'Withdrawal for order' => 'Списание за заказ',
    'Payment for order' => 'Оплата за заказ',
    'Withdrawal' => 'Вывод средств',
    'Payment' => 'Ввод средств',
    'User not found!' => 'Пользователь не найден!',
    'Active' => 'Активные',
    'Completed' => 'Завершённые',
    'Executed by me' => 'Выполненные мной',
    'active' => 'активных',
    'completed' => 'завершённых',
    'New order creation' => 'Создание нового заказа',
    'Header' => 'Заголовок',
    'Short description' => 'Краткое описание заказа',
    'Description' => 'Описание',
    'Please describe what needs to be done' => 'Пожалуйста, развёрнуто опишите, что нужно будет сделать',
    'Payment' => 'Оплата',
    'by order price' => 'по сумме заказа',
    'by executer revenue' => 'по сумме исполнителю',
    'This is the price you will pay for your order.' => 'Это сумма, которую вы заплатите за выполнение заказа.',
    'This is the amount the executer will receive.' => 'Это сумма, которую получит исполнитель.',
    'Site comission' => 'Комиссия сайта',
    'executer will get' => 'исполнитель получит',
    'you will pay' => 'вы заплатите',
    'Cancel' => 'Отмена',
    'Your order is created and already visible on main page.' => 'Ваш заказ успешно размещен и уже появился на главной странице.',
    'To my orders' => 'К моим заказам',
    'New order' => 'Новый заказ',
    'Close' => 'Закрыть',
    'Order price:' => 'Стоимость заказа:',
    'Executer' => 'Исполнитель',
    'Executer will get' => 'Исполнитель получит',
    'Executer got' => 'Исполнитель получил',
    'Signup on website' => 'Регистрация на сайте',
    'How can we address you?' => 'Как нам к вам обращаться?',
    'We will send email with password here' => 'На него придет письмо с паролем',
    'If you leave empty then we will generate password for you' => 'Можно не вводить, тогда мы сами вам придумаем',
    'You have successfully signed up on orderist.' => 'Вы зарегистировались на сайте Заказист.',
    'Your data:' => 'Ваши данные:',
    'Go to website' => 'Перейти на сайт',
    'Debug enabled' => 'Отладка включена',
    'Debug disabled' => 'Отладка выключена',
    'Everything is OK' => 'Всё ОК',
    'Fixed %s errors' => 'Исправили %s ошибок',
    'Name is too short.' => 'Слишком короткое имя.',
    'Name is too long.' => 'Слишком длинное имя.',
    'Password is too short.' => 'Слишком короткий пароль.',
    'Email is invalid.' => 'Вы ввели недопустимый Email.',
    'User with this Email is already registered.' => 'Пользователь с таким Email уже зарегисрирован.',
    'Error, order not found!' => 'Ошибка, заказ не найден!',
    'Error, you don\'t have access to this order!' => 'Ошибка, у вас нет доступа к этому заказу!',
    'To create orders you need to login or signup.' => 'Для создания заказов вам необходимо войти в свой профиль или зарегистрироваться на сайте.',
    'Provide order title!' => 'Введите заголовок заказа!',
    'Title is too long!' => 'Сликом длинный заголовок, будьте лаконичней!',
    'Provide order price!' => 'Укажите стоимость заказа!',
    'Price is too low!' => 'Cлишком низкая стоимость заказа!',
    'Executer will receive nothing!' => 'Ну нельзя же так, чтобы исполнитель ничего не получил!',
    'Oops, there is error in price calculation.' => 'Ой, что-то мы не так рассчитали вам оплату заказа. Это наш косяк, просто попробуйте указать другую сумму.',
    'Error, please try again.' => 'Произошла ошибка, попробуйте ещё раз.',
    'To execute orders you need to login or signup.' => 'Для выполнения заказов вам необходимо войти в свой профиль или зарегистрироваться на сайте.',
    'Error, order does not exist!' => 'Ошибка, такого заказа не существует!',
    'Sorry, this order was executed by someone else!' => 'Извините, но этот заказ уже выполнен кем-то другим!',
    'Do not try to delete not your orders!' => 'Мы обнаружили, что вы пытаетесь удалить чужой заказ. Не стоит этого делать!',
    'Sorry, you can not delete completed order!' => 'Извините, но нельзя удалить уже выполненный заказ!',
    'Our revenue:' => 'Наш доход с комисии:',
    'Check data consistency' => 'Проверить целостность данных',
    'Wrong Email or password.' => 'Неправильный Email или пароль.',
];

/*
'Error, please try again!': 'Ошибка, попробуйте ещё раз!',
Confirmation Подтверждение
Cancel Отмена
Error Произошла ошибка
Close Закрыть
Expand Показать полностью
USD руб.
Thanks! You earned Спасибо, ваш счет пополнен на
Order completed Заказ выполнен
You earned: Вы получили:
Do you want to delete order? Вы уверены, что хотите удалить заказ?
Cancel Отмена
Delete Удалить
Order completed Заказ выполнен
Order deleted Заказ удалён
*/

$translationKeys = [
    'welcome_on_this_site' => [
        'en' => 'On this website you can find contractor for any of your <a href="/order/create/" onclick="orderist.order.createPopup.open(); return false;">orders</a>,<br/>and take <a href="/orders/">other orders</a> and earn money.</p>',
        'ru' => 'На этом сайте вы сможете найти исполнителей на любой <a href="/order/create/" onclick="orderist.order.createPopup.open(); return false;">свой заказ</a>, а также выполнять <a href="/orders/">заказы других</a> и зарабатывать на этом деньги.</p>',
    ],
    'balance_locked_funds' => [
        'en' => 'The funds are locked when you create order, until someone completes it. If you delete active orders, then all funds will be unlocked.',
        'ru' => 'Когда вы создаёте заказ, то деньги не списываются с вашего счета, а просто блокируются на время, пока кто-то его не выполнит. Если вы удалите активные заказы, то все деньги вернутся вам на счёт.',
    ],
    'orders_no_completed' => [
        'en' => 'You don\'t have completed orders, but you can <a href="/orders/">find order</a> right now.',
        'ru' => 'У вас нет выполненных заказов, но вы можете <a href="/orders/">найти себе заказ</a> прямо сейчас.',
    ],
    'orders_no_orders' => [
        'en' => 'You have no%s orders, but you can <a href="/order/create/" onclick="orderist.order.createPopup.open(); return false;">create new order</a>.',
        'ru' => 'У вас нет%s заказов, но вы можете <a href="/order/create/" onclick="orderist.order.createPopup.open(); return false;">разместить новый заказ</a>.',
    ],
    'first_time_here' => [
        'en' => 'First time here? Then <a href="/user/signup/" onclick="orderist.user.signup(); return false;">signup</a>',
        'ru' => 'Первый раз тут? Тогда <a href="/user/signup/" onclick="orderist.user.signup(); return false;">зарегистрируйтесь</a>',
    ],
    'already_signed_up' => [
        'en' => 'Already signed up? Then <a href="/user/login/" onclick="orderist.user.login(); return false;">login</a> to your profile',
        'ru' => 'Уже зарегистрированы? Тогда <a href="/user/login/" onclick="orderist.user.login(); return false;">войдите</a> в свой профиль',
    ],
    'signup_signup' => [
        'en' => 'Signup',
        'ru' => 'Зарегистрироваться',
    ],
    'signup_login' => [
        'en' => 'Login',
        'ru' => 'Вход в профиль',
    ],
    'order_not_enough_money' => [
        'en' => 'Sorry, you lack <b>%s USD</b> to create order!<br>Try to lower price or <b><a href="/user/cash/" onclick="orderist.order.createPopup.addCash(\'%s\'); return false;">topup your balance</a></b>.',
        'ru' => 'Ой, вам на хватает <b>%s руб.</b> для создания заказа!<br>Попробуйте снизить стоимость или <b><a href="/user/cash/" onclick="orderist.order.createPopup.addCash(\'%s\'); return false;">пополнить счет</a></b>.',
    ],
    'order_is_yours' => [
        'en' => 'Please do not try to execute your own order.<br/>
                If you want to delete it then visit <a href="/user/orders/">My orders</a>, or
                <a href="/user/orders/" onclick="orderist.order.deleteConfirm(\'%s\'); event.stopPropagation(); return false;">delete right now</a>.',
        'ru' => 'Мы обнаружили, что вы пытаетесь выполнить свой же заказ.<br/>
                Не стоит этого делать! Но если вы хотели удалить свой заказ, то это можно сделать на странице <a href="/user/orders/">Мои заказы</a>, либо
                можно <a href="/user/orders/" onclick="orderist.order.deleteConfirm(\'%s\'); event.stopPropagation(); return false;">удалить прямо сейчас</a>.',
    ],
];

$GLOBALS['i18n_translation_ru'] = $translationRu;
$GLOBALS['i18n_translation_keys'] = $translationKeys;

function _g($text) {
    $locale = $GLOBALS['i18n_locale'];
    if ($locale == 'ru') {
        $translationRu = $GLOBALS['i18n_translation_ru'];
        $translated = !empty($translationRu[$text]) ? $translationRu[$text] : false;
    }
    if (empty($translated)) {
        $translated = _gk($text);
    }
    return !empty($translated) ? $translated : $text;
}

function _f() {
    $args = func_get_args();
    $text = $args[0];
    $args = array_slice($args, 1);

    $text = _g($text);

    foreach ($args as $arg) {
        $needle = '%s';
        $pos = strpos($text, $needle);
        if ($pos !== false) {
            $text = substr_replace($text, $arg, $pos, strlen($needle));
        }
    }

    return $text;
}

function _gk($text) {
    $locale = $GLOBALS['i18n_locale'];
    $translations = $GLOBALS['i18n_translation_keys'];
    $map = !empty($translations[$text]) ? $translations[$text] : false;
    $translated = !empty($map[$locale]) ? $map[$locale] : false;
    return !empty($translated) ? $translated : $text;
}

function _tr($text) {
    $locale = $GLOBALS['i18n_locale'];
    if ($locale == 'en') {
        $translated = translit($text);
    }
    return !empty($translated) ? $translated : $text;
}

function translit($value)
{
    $converter = [
        'а' => 'a',    'б' => 'b',    'в' => 'v',    'г' => 'g',    'д' => 'd',
        'е' => 'e',    'ё' => 'e',    'ж' => 'zh',   'з' => 'z',    'и' => 'i',
        'й' => 'y',    'к' => 'k',    'л' => 'l',    'м' => 'm',    'н' => 'n',
        'о' => 'o',    'п' => 'p',    'р' => 'r',    'с' => 's',    'т' => 't',
        'у' => 'u',    'ф' => 'f',    'х' => 'h',    'ц' => 'c',    'ч' => 'ch',
        'ш' => 'sh',   'щ' => 'sch',  'ь' => '',     'ы' => 'y',    'ъ' => '',
        'э' => 'e',    'ю' => 'yu',   'я' => 'ya',
        'А' => 'A',    'Б' => 'B',    'В' => 'V',    'Г' => 'G',    'Д' => 'D',
        'Е' => 'E',    'Ё' => 'E',    'Ж' => 'Zh',   'З' => 'Z',    'И' => 'I',
        'Й' => 'Y',    'К' => 'K',    'Л' => 'L',    'М' => 'M',    'Н' => 'N',
        'О' => 'O',    'П' => 'P',    'Р' => 'R',    'С' => 'S',    'Т' => 'T',
        'У' => 'U',    'Ф' => 'F',    'Х' => 'H',    'Ц' => 'C',    'Ч' => 'Ch',
        'Ш' => 'Sh',   'Щ' => 'Sch',  'Ь' => '',     'Ы' => 'Y',    'Ъ' => '',
        'Э' => 'E',    'Ю' => 'Yu',   'Я' => 'Ya',
    ];
    $value = strtr($value, $converter);
    return $value;
}