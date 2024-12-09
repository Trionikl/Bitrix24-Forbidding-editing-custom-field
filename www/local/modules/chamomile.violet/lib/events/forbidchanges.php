<?php

/**
 * Обработка соббытия редактирования HL-блока со списком адресов
 */

namespace Chamomile\Violet\Events;


use Bitrix\Main\Loader;
use Bitrix\Iblock\Elements\ElementTheBanEditingApiTable;


/**
 * ForbidChanges
 */
class ForbidChanges
{
    /**
     * Deletes the cache for the list of user addresses when a Highload Block (HL-block) is edited.
     *
     * @param int $idHlBlock The ID of the edited HL-block.
     *
     * @return void
     */
    public static function notSave(&$event)
    {
        // получить текущего пользователя
        $userId = \Bitrix\Main\Engine\CurrentUser::get()->getId();

        // Получить список пользователей для которых запрещено редактирование
        Loader::includeModule('iblock');
        //получить id элемента инфоблока
        $elements = ElementTheBanEditingApiTable::getList([
            'select' => ['ID', 'NAME'],
            'filter' => ['=ACTIVE' => 'Y',],
        ])->fetchAll();
        foreach ($elements as $element) {
            $elementId = $element['ID'];
            break;
        }
        $elements = ElementTheBanEditingApiTable::getList([
            'select' => ['ID', 'NAME', 'DETAIL_PICTURE', 'PROPERTY_USER_FORBIDDEN'],
            'filter' => [
                'ID' => $elementId,
            ],
        ])->fetchCollection();
        //getPropertyUserForbidden - сормирован как "get" и код пользовательского свойства "PROPERTY_USER_FORBIDDEN" записанный в вербблюжей нотации 
        foreach ($elements as $element) {
            foreach ($element->getPropertyUserForbidden()->getAll() as $value) {
                $arResult[] = $value->getValue();
            }
        }

        //проверяем находится ли пользователь в списке запрещённых, если да то удаляем поле перед сохранением
        if (in_array($userId, $arResult)) {
            unset($event['UF_MY_CUSTOM_FIELD']);
        }
    }
}