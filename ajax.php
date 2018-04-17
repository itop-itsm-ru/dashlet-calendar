<?php

require_once('../../approot.inc.php');

try {
    require_once(APPROOT . '/application/startup.inc.php');
    require_once(APPROOT . '/application/loginwebpage.class.inc.php');
    LoginWebPage::DoLogin(); // Check user rights and prompt if needed
    $sStartIntervalDate = utils::ReadParam('start', '');
    $sStartDateAttr = utils::ReadParam('start_attr', '');
    $sEndIntervalDate = utils::ReadParam('end', '');
    $sEndDateAttr = utils::ReadParam('end_attr', '');
    $bShowUnfinished = (bool)utils::ReadParam('unfinished', false);
    $sTitleAttr = utils::ReadParam('title_attr', '');
    $sDescriptionAttr = utils::ReadParam('description_attr', '');
    $sFilter = utils::ReadParam('filter', '');
    $oFilter = DBObjectSearch::unserialize($sFilter);
    $sClass = $oFilter->GetClassAlias();
    if ($sEndDateAttr && !$bShowUnfinished)
    {
        // выбраны атрибуты начала и окончания и не показываются незавершенные
        // В этом случае выбираем все, у которых дата окончания больше даты начала интервала
        // и дата начала меньше даты окончания интерала
        $oFilter->AddCondition($sStartDateAttr, $sEndIntervalDate, '<');
        $oFilter->AddCondition($sEndDateAttr, $sStartIntervalDate, '>');
    }
    elseif ($sEndDateAttr && $bShowUnfinished)
    {
        // выбраны атрибуты начала и окончания и показываются незавершенные
        // В этом случае выбираем все, у которых дата окончания больше даты начала интервала или пустая,
        // а дата начала меньше даты окончания интервала
        $oFilter->AddCondition($sStartDateAttr, $sEndIntervalDate, '<');
        $sOQLCondition = "$sClass.$sEndDateAttr > '$sStartIntervalDate' OR ISNULL($sClass.$sEndDateAttr)";
        $oExpr = Expression::FromOQL($sOQLCondition);
        $oFilter->AddConditionExpression($oExpr);
    }
    else
    {
        // выбран только атрибут начала, ищем только то, что попало в интервал
        $sOQLCondition = "$sClass.$sStartDateAttr > '$sStartIntervalDate' AND $sClass.$sStartDateAttr < '$sEndIntervalDate'";
        $oExpr = Expression::FromOQL($sOQLCondition);
        $oFilter->AddConditionExpression($oExpr);

        // Код ниже не работает, т.к. в запросе появляется один и тот же алиас :start_date (название поля)
        // и, соответственно, одно и то же его значение
        //$oFilter->AddCondition($sStartDateAttr, $sStartIntervalDate, '>');
        //$oFilter->AddCondition($sStartDateAttr, $sEndIntervalDate, '<');
    }
    $oObjectSet = new DBObjectSet($oFilter);
    $aEvents = array();
    while ($oObj = $oObjectSet->Fetch()) {
        $aEvent = array();
        $aEvent['title'] = strip_tags(html_entity_decode($oObj->Get($sTitleAttr))) . ($sDescriptionAttr ? "\n" . strip_tags(html_entity_decode($oObj->Get($sDescriptionAttr))) : '');
        $aEvent['start'] = $oObj->Get($sStartDateAttr);
        $sEndDate = '';
        if ($sEndDateAttr) {
            $sEndDate = $oObj->Get($sEndDateAttr);
            // TODO: Date format
            if (!$sEndDate && $bShowUnfinished) $sEndDate = date_format(new DateTime(), 'Y-m-d H:i:s');
        }
        $aEvent['end'] = $sEndDate;
        $aEvent['url'] = ApplicationContext::MakeObjectUrl(get_class($oObj), $oObj->GetKey());
        $aEvents[] = $aEvent;
    }
    $jsonEvents = json_encode($aEvents);
    echo $jsonEvents;
} catch (Exception $e) {
    echo $e->getMessage();
}