<?php

// Разработка начинается с папки установки и файла index.php
// Индексный файл в папке install - основной файл установки, в котором прописывается класс модуля, функции установки, удаления, работа с этапами этих процессов
// В начале подключаются классы битрикса, которые будут использоваться и файлы локализации

use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\Loader;
use \Bitrix\Main\Application;
use \Bitrix\Main\ModuleManager;
use CIBlock;
use CIBlockProperty;
use CIBlockElement;
use Bitrix\Main\UserField\Types\StringType;
use CUserTypeEntity;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\IblockTable;


Loc::loadMessages(__FILE__);

// Имя класса должно проецироваться от айди модуля (имени папки), точка заменяется на нижнее подчеркивание - обязательное условие.
// И должен наследоваться от CModule.
/**
 * chamomile_violet
 */
class chamomile_violet extends CModule
{
    public $arResponse = [
        "STATUS" => true,
        "MESSAGE" => ""
    ];

    /**
     * setResponse
     *
     * @param  mixed $status
     * @param  mixed $message
     * @return void
     */
    public function setResponse($status, $message = "")
    {
        $this->arResponse["STATUS"] = $status;
        $this->arResponse["MESSAGE"] = $message;
    }

    /**
     * __construct
     *
     * @return void
     */
    function __construct()
    {
        $arModuleVersion = array();

        // Подключение файла версии, который содержит массив для модуля
        require(__DIR__ . "/version.php");

        // Поля заполняются в переменных класса для удобства работы
        $this->MODULE_ID = "chamomile.violet"; // Имя модуля

        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        $this->MODULE_NAME = Loc::getMessage("CHAMOMILE_MODULE_NAME");
        $this->MODULE_DESCRIPTION = Loc::getMessage("CHAMOMILE_MODULE_DESCRIPTION");

        // Имя партнера создавшего модуль (Выводится информация в списке модулей о человеке или компании, которая создала этот модуль)
        $this->PARTNER_NAME = Loc::getMessage("CHAMOMILE_PARTNER_NAME");
        $this->PARTNER_URI = Loc::getMessage("CHAMOMILE_PARTNER_URI");

        // Если указано, то на странице прав доступа будут показаны администраторы и группы (страницу сначала нужно запрограммировать)
        $this->SHOW_SUPER_ADMIN_GROUP_RIGHTS = "Y";
        // Если указано, то на странице редактирования групп будет отображаться этот модуль
        $this->MODULE_GROUP_RIGHTS = "Y";

        $this->elementId = null;
        $this->iblockId = null;
    }

    // Установка баз данных    
    /**
     * installDB
     *
     * @return void
     */
    function installDB()
    {
        Loader::includeModule($this->MODULE_ID);

        // Подписка на событие обновления сделки
        // таблица событий в Битрикс "b_module_to_module"
        $eventManager = \Bitrix\Main\EventManager::getInstance();
        $eventManager->registerEventHandler(
            'crm',
            'OnBeforeCrmDealUpdate',
            $this->MODULE_ID,
            '\\Chamomile\\Violet\\Events\\ForbidChanges',
            'notSave'
        );

        // Создание универсального списка
        Loader::includeModule('iblock');

        // Создаем массив с данными для создания инфоблока
        $iblockData = [
            'NAME' => 'Запрет на изменение поля в сделках', // Название инфоблока
            'CODE' => 'the_ban_editing', // Символьный код инфоблока
            'API_CODE' => 'theBanEditingApi', // Символьный код API
            'IBLOCK_TYPE_ID' => 'lists', // Тип инфоблока (списка)
            'SITE_ID' => ['s1'], // ID сайта, на котором будет доступен инфоблок
            'ACTIVE' => 'Y', // Активность инфоблока
            'VERSION' => 2, // Версия инфоблока (2 - множественные свойства)
            'GROUP_ID' => ['2' => 'R'], // Группы пользователей с правами доступа
        ];

        // Добавляем инфоблок
        $iblock = new CIBlock;
        $iblockId = $iblock->Add($iblockData);

        if ($iblockId) {
            \Bitrix\Main\Diag\Debug::writeToFile("Инфоблок успешно создан с ID: " . $iblockId, $varName = __DIR__, $fileName = "/local/debug/debug.log");
        } else {
            \Bitrix\Main\Diag\Debug::writeToFile("Ошибка при создании инфоблока: " . $iblock->LAST_ERROR, $varName = __DIR__, $fileName = "/local/debug/debug.log");
        }

        // Создаем массив с данными для добавления элемента
        $elementData = [
            'IBLOCK_ID' => $iblockId, // ID инфоблока
            'NAME' => 'Пользователи с запретом', // Название элемента
            'ACTIVE' => 'Y', // Активность элемента
            'CODE' => 'users_with_ban', // Символьный код элемента
            'SORT' => 500, // Сортировка
            'PREVIEW_TEXT' => '', // Превью текст
            'DETAIL_TEXT' => '', // Детальный текст
        ];

        // Добавляем элемент в инфоблок
        $ibElement = new CIBlockElement;
        $elementId = $ibElement->Add($elementData);

        if ($elementId) {
            \Bitrix\Main\Diag\Debug::writeToFile("Элемент успешно добавлен с ID: " . $elementId, $varName = __DIR__, $fileName = "/local/debug/debug.log");
        } else {
            \Bitrix\Main\Diag\Debug::writeToFile("Ошибка при добавлении элемента: " . $ibElement->LAST_ERROR, $varName = __DIR__, $fileName = "/local/debug/debug.log");
        }

        $propertyData = [
            "SORT" => "600", // Сортировка
            'NAME' => 'Пользователи', // Название поля
            'CODE' => 'PROPERTY_USER_FORBIDDEN', // Символьный код поля
            "IS_REQUIRED" => "N", // Обязательное поле
            'TYPE' => 'N', // Тип поля (строка)
            'USER_TYPE' => 'UserID', // Тип пользовательского поля (Привязка к сотруднику)
            'MULTIPLE' => 'Y', // Множественное поле
            'MULTIPLE_CNT' => 1, // Количество полей для ввода новых множественных значений  
            'SHOW_IN_LIST' => 'Y', // Отображение в списке
        ];

        // Добовление пользовательского поля в универсальный список
        Loader::includeModule('lists');

        $obList = new CList($iblockId);
        $field_id = $obList->AddField($propertyData);
        if ($field_id) {
            \Bitrix\Main\Diag\Debug::writeToFile("Поле успешно добавлено с ID: " . $field_id, $varName = __DIR__, $fileName = "/local/debug/debug.log");
        } else {
            \Bitrix\Main\Diag\Debug::writeToFile("Ошибка при добавлении поля: " . $obList->LAST_ERROR, $varName = __DIR__, $fileName = "/local/debug/debug.log");
        }

        //создание пользовательско поля в сделке
        // Подключаем модуль crm
        Loader::includeModule('crm');

        $userFieldCode = 'UF_MY_CUSTOM_FIELD'; //Символьный код вашего пользовательского поля

        // Создаем новое пользовательское поле
        $arField = [
            'ENTITY_ID' => 'CRM_DEAL',
            'FIELD_NAME' =>  $userFieldCode,
            'USER_TYPE_ID' => 'string', // Тип поля (string, integer, double, datetime, boolean, etc.)
            'EDIT_FORM_LABEL' => [
                'ru' => 'Мое пользовательское поле',
                'en' => 'My custom field',
            ],
            'LIST_COLUMN_LABEL' => [
                'ru' => 'Мое пользовательское поле',
                'en' => 'My custom field',
            ],
            'LIST_FILTER_LABEL' => [
                'ru' => 'Мое пользовательское поле',
                'en' => 'My custom field',
            ],
            'SETTINGS' => [
                'DEFAULT_VALUE' => '', // Значение по умолчанию
            ],
        ];

        //проверка существования пользовательского поля
        // Получаем пользовательское поле по символьному коду
        $userTypeEntity = new \CUserTypeEntity();
        $dbRes = $userTypeEntity->GetList(
            [],
            ['FIELD_NAME' => $userFieldCode]
        );

        $userField = $dbRes->Fetch();

        // Добавляем пользовательское поле в сделки
        if (!$userField) {
            $obUserField = new CUserTypeEntity;
            $fieldId = $obUserField->Add($arField);
            if ($fieldId) {
                \Bitrix\Main\Diag\Debug::writeToFile("Поле успешно создано с ID: " . $fieldId, $varName = __DIR__, $fileName = "/local/debug/debug.log");
            } else {
                \Bitrix\Main\Diag\Debug::writeToFile("Ошибка при создании поля", $varName = __DIR__, $fileName = "/local/debug/debug.log");
            }
        }
        $this->elementId = $elementId;
        $this->iblockId = $iblockId;
    }

    // При установке
    function installEvents() {}

    // Копирование файлов    
    /**
     * installFiles
     *
     * @return void
     */
    function installFiles()
    {
        return true;
    }

    // Заполнение таблиц тестовыми данными    
    /**
     * addTestData
     *
     * @return void
     */
    function addTestData()
    {
        Loader::includeModule($this->MODULE_ID);

        // Добавляем значение 1 в множественное поле с кодом "PROPERTY_USER_FORBIDDEN"
        $ibElement = new CIBlockElement;
        $ibElement->SetPropertyValuesEx($this->elementId, $this->iblockId, [
            'PROPERTY_USER_FORBIDDEN' => [1], // Значение 1
        ]);

        return true;
    }

    // Для удобства проверки результата    
    /**
     * checkAddResult
     *
     * @param  mixed $result
     * @return void
     */
    function checkAddResult($result)
    {
        if ($result->isSuccess()) {
            return [true, $result->getId()];
        }

        return [false, $result->getErrorMessages()];
    }

    // Основная функция установки, должна называться именно так, поэтапно производим установку нашего модуля    
    /**
     * DoInstall
     *
     * @return void
     */
    function DoInstall()
    {
        global $APPLICATION;

        // Пример с установкой в один шаг:
        // Если необходимо использовать ORM сущности при установке (например для создания таблицы в бд), то нужно регистрировать его до вызова создания таблиц и т.п.
        // Иначе не сможем использовать неймспейсы
        // ModuleManager::registerModule($this->MODULE_ID);
        // $this->installDB();
        // $this->installEvents();
        // $this->installAgents();
        // if (!$this->installFiles())
        //     $APPLICATION->ThrowException($this->arResponse["MESSAGE"]);
        // if (file_exists($_SERVER["DOCUMENT_ROOT"] . "/local/modules/chamomile.violet/install/step.php"))
        //     $APPLICATION->IncludeAdminFile(Loc::getMessage("CHAMOMILE_INSTALL_TITLE"), $_SERVER["DOCUMENT_ROOT"] . "/local/modules/chamomile.violet/install/step.php");
        // else
        //     $APPLICATION->IncludeAdminFile(Loc::getMessage("CHAMOMILE_INSTALL_TITLE"), $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/chamomile.violet/install/step.php");

        // Пример с установкой в несколько шагов:
        // Получаем контекст и из него запросы
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        // Проверяем какой сейчас шаг, если он не существует или меньше 2, то выводим первый шаг установки
        if ($request["step"] < 2) {
            if (file_exists($_SERVER["DOCUMENT_ROOT"] . "/local/modules/chamomile.violet/install/step1.php"))
                $APPLICATION->IncludeAdminFile(Loc::getMessage("CHAMOMILE_INSTALL_TITLE"), $_SERVER["DOCUMENT_ROOT"] . "/local/modules/chamomile.violet/install/step1.php");
            else
                $APPLICATION->IncludeAdminFile(Loc::getMessage("CHAMOMILE_INSTALL_TITLE"), $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/chamomile.violet/install/step1.php");
        } elseif ($request["step"] == 2) {
            // Если шаг второй, то приступаем к установке
            // Если необходимо использовать ORM сущности при установке (например для создания таблицы в бд), то нужно регистрировать его до вызова создания таблиц и т.п.
            // Иначе не сможем использовать неймспейсы

            // Глянуть все языковые константы по установке и удалению модулей - https://github.com/devsandk/bitrix_utf8/blob/master/bitrix/modules/main/lang/ru/admin/partner_modules.php

            ModuleManager::registerModule($this->MODULE_ID);
            $this->installDB();
            if (!$this->installFiles())
                $APPLICATION->ThrowException($this->arResponse["MESSAGE"]);
            if ($request["add_data"] == "Y") {
                $result = $this->addTestData();
                if ($result !== true)
                    $APPLICATION->ThrowException($result);
            }
            if (file_exists($_SERVER["DOCUMENT_ROOT"] . "/local/modules/chamomile.violet/install/step2.php"))
                $APPLICATION->IncludeAdminFile(Loc::getMessage("CHAMOMILE_INSTALL_TITLE"), $_SERVER["DOCUMENT_ROOT"] . "/local/modules/chamomile.violet/install/step2.php");
            else
                $APPLICATION->IncludeAdminFile(Loc::getMessage("CHAMOMILE_INSTALL_TITLE"), $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/chamomile.violet/install/step2.php");
        }
    }

    // Удаление файлов    
    /**
     * unInstallFiles
     *
     * @return void
     */
    function unInstallFiles()
    {
        return true;
    }


    /**
     * unInstallDB
     *
     * @return void
     */
    function unInstallDB()
    {
        Loader::includeModule($this->MODULE_ID);

        // удаление события
        $eventManager = \Bitrix\Main\EventManager::getInstance();
        $eventManager->unRegisterEventHandler(
            'crm',
            'OnBeforeCrmDealUpdate',
            $this->MODULE_ID,
            '\\Chamomile\\Violet\\Events\\ForbidChanges',
            'notSave'
        );

        //удаление поля из битрикс24 'UF_MY_CUSTOM_FIELD'
        // Подключаем модуль main
        Loader::includeModule('main');

        try {
            // Символьный код пользовательского поля, которое нужно удалить
            $userFieldCode = 'UF_MY_CUSTOM_FIELD'; // Замените на фактический символьный код вашего пользовательского поля

            // Получаем пользовательское поле по символьному коду
            $userTypeEntity = new \CUserTypeEntity();
            $dbRes = $userTypeEntity->GetList(
                [],
                ['FIELD_NAME' => $userFieldCode]
            );

            $userField = $dbRes->Fetch();

            if ($userField) {
                // ID пользовательского поля
                $userFieldId = $userField['ID'];

                // Удаляем пользовательское поле
                $result = $userTypeEntity->Delete($userFieldId);

                if ($result) {
                    \Bitrix\Main\Diag\Debug::writeToFile("'Пользовательское поле UF_MY_CUSTOM_FIELD успешно удалено.'", $varName = __DIR__, $fileName = "/local/debug/debug.log");
                } else {
                    throw new Exception(\Bitrix\Main\Diag\Debug::writeToFile('Ошибка при удалении пользовательского поля.', $varName = __DIR__, $fileName = "/local/debug/debug.log"));
                }
            } else {
                \Bitrix\Main\Diag\Debug::writeToFile('Пользовательское поле с кодом ' . $userFieldCode . ' не найдено.', $varName = __DIR__, $fileName = "/local/debug/debug.log");
            }
        } catch (Exception $e) {
            echo 'Ошибка: ' . $e->getMessage();
        }


        //Удаление созданного инфоблока
        $arIblock = \Bitrix\Iblock\IblockTable::getList(array(
            'select' => array('ID'), // поля для выборки
            'filter' => array('CODE' => 'the_ban_editing') // параметры фильтра
        ))->fetch();
        if ($arIblock) {
            \Bitrix\Iblock\IblockTable::delete($arIblock['ID']);
            \Bitrix\Main\Diag\Debug::writeToFile("'Пользовательское the_ban_editing поле успешно удалено.'", $varName = __DIR__, $fileName = "/local/debug/debug.log");
        } else {
            \Bitrix\Main\Diag\Debug::writeToFile("'Пользовательское поле не найдено.'", $varName = __DIR__, $fileName = "/local/debug/debug.log");
        }
    }

    // Основная функция удаления, должна называться именно так, поэтапно производим удаление нашего модуля    
    /**
     * DoUninstall
     *
     * @return void
     */
    function DoUninstall()
    {
        global $APPLICATION;

        // Получаем контекст и из него запросы
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();

        // Проверяем какой сейчас шаг, если он не существует или меньше 2, то выводим первый шаг удаления
        if ($request["step"] < 2) {
            if (file_exists($_SERVER["DOCUMENT_ROOT"] . "/local/modules/chamomile.violet/install/unstep1.php"))
                $APPLICATION->IncludeAdminFile(Loc::getMessage("CHAMOMILE_UNINSTALL_TITLE"), $_SERVER["DOCUMENT_ROOT"] . "/local/modules/chamomile.violet/install/unstep1.php");
            else
                $APPLICATION->IncludeAdminFile(Loc::getMessage("CHAMOMILE_UNINSTALL_TITLE"), $_SERVER["DOCUMENT_ROOT"] . "/local/modules/chamomile.violet/install/unstep1.php");
        } elseif ($request["step"] == 2) {
            // Если шаг второй, то приступаем к удалению
            if ($request["save_data"] != "Y")
                $this->unInstallDB();
            if (!$this->unInstallFiles())
                $APPLICATION->ThrowException($this->arResponse["MESSAGE"]);
            ModuleManager::unRegisterModule($this->MODULE_ID);
            if (file_exists($_SERVER["DOCUMENT_ROOT"] . "/local/modules/chamomile.violet/install/unstep2.php"))
                $APPLICATION->IncludeAdminFile(Loc::getMessage("CHAMOMILE_UNINSTALL_TITLE"), $_SERVER["DOCUMENT_ROOT"] . "/local/modules/chamomile.violet/install/unstep2.php");
            else
                $APPLICATION->IncludeAdminFile(Loc::getMessage("CHAMOMILE_UNINSTALL_TITLE"), $_SERVER["DOCUMENT_ROOT"] . "/local/modules/chamomile.violet/install/unstep2.php");
        }
    }

    // Функция для определения возможных прав
    // Если не задана, то будут использованы стандартные права (D,R,W)
    // Должна называться именно так и возвращать массив прав и их названий    
    /**
     * GetModuleRightList
     *
     * @return void
     */
    function GetModuleRightList()
    {
        return array(
            "reference_id" => array("D", "K", "S", "W"),
            "reference" => array(
                "[D] " . Loc::getMessage("CHAMOMILE_DENIED"),
                "[K] " . Loc::getMessage("CHAMOMILE_READ_COMPONENT"),
                "[S] " . Loc::getMessage("CHAMOMILE_WRITE_SETTINGS"),
                "[W] " . Loc::getMessage("CHAMOMILE_FULL"),
            )
        );
    }
}