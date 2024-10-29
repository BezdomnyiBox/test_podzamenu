## Требования для запуска:
1. PHP версии 7.4 или выше
2. Composer
3. Symfony CLI (рекомендуется для локальной разработки)
4. Расширение PHP curl

## Инструкция:
### Склонируйте репозиторий на ваш локальный компьютер:
```git clone https://github.com/BezdomnyiBox/test_podzamenu.git```
### Перейдите на директорию проекта:
```cd your-repository```
### Убедитесь, что у вас установлен Composer. Затем выполните команду:
```composer install```
### Запустите локальный сервер:
```symfony serve```
### В браузере перейдите по ссылке:
```http://localhost:8000/product/search```
Необходимо:
- ввести Артикул например: 554092
- ввести API Key например: of6Zg...и т.д.


## Структура проекта:
1. src/Controller/ProductController.php: Контроллер, обрабатывающий запросы поиска и отображения результатов.
2. templates/product/search.html.twig: Шаблон Twig для отображения формы поиска.
3. templates/product/results.html.twig: Шаблон Twig для отображения результатов.
4. config/routes.yaml: Настройки маршрутов для обработки запросов.
