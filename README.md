## Описание

Вы являетесь межгалактическим императором, который распространяет своё влияние посредством различных стратегий на множество галактик. Вы начинаете на своей собственой планете и строите там экономическую и военную инфраструктуру. Исследования дают Вам доступ к новым технологиям и более совершенным системам вооружения.

## Адрес игры

https://xnova.su/
https://x.xnova.su/

## Framework

Phalcon

## Системные требования:
- PHP 7.0 и выше
- MySQL 5.7 и выше
- Phalcon 3.3 и выше
- NodeJs

**Рекомендуется использовать связку Nginx + php-fpm для максимальной производительности**

## Установка
1. Скомпилировать и установить PHP расширение **Phalcon** (**https://phalconphp.com/ru/download**)
2. Настроить Nginx (**https://docs.phalconphp.com/ru/latest/webserver-setup#nginx**)
2.1. Или настроить Apache (**https://docs.phalconphp.com/ru/latest/webserver-setup#apache**)
3. Залить скрипты игры на сервер (с использованием git или напрямую)
4. Залить базу данных (**install/db.sql**)
5. Переименовать или скопировать конфиг **app/config/_.core.ini** в **core.ini**
6. Внести параметры подключения к базе данных в файле **app/config/core.ini**
7. Установить NodeJS
8. Перейти в корневой каталог проекта и установить зависимости **npm install**
9. Установить Composer **https://getcomposer.org/**
10. Перейти в корневой каталог проекта и установить зависимости **composer install**
11. Скомпилировать стили командой **gulp**
12. Настроить cron **install/cron.conf**
12. Логин и пароль администратора **admin@xnova.su** / **123456**