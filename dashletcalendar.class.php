<?php

class DashletCalendar extends Dashlet
{
    protected $sModuleName;
    protected $sModuleUrlBase;
    protected $sUrl;
    protected $iEventResourcesCount;

    public function __construct($oModelReflection, $sId)
    {
        $this->sModuleName = $this->GetModuleName();
        $this->sModuleUrlBase = 'env-' . utils::GetCurrentEnvironment() . '/' . $this->sModuleName . '/';
        $this->sUrl = '../' . $this->sModuleUrlBase . 'ajax.php';
        $this->iEventResourcesCount = utils::GetConfig()->GetModuleSetting($this->sModuleName, 'resources_count', 3);

        parent::__construct($oModelReflection, $sId);
        $this->aProperties['title'] = Dict::S('UI:WorkOrderCalendar:Title');
        $this->aProperties['default_view'] = 'month';
        $this->aProperties['agenda_day'] = false;
        $this->aProperties['agenda_week'] = false;
        $this->aProperties['list_period'] = 'listWeek';

        $this->aProperties['enabled_1'] = true;
        $this->aProperties['query_1'] = 'SELECT WorkOrder';
        $this->aProperties['start_attr_1'] = 'start_date';
        $this->aProperties['end_attr_1'] = 'end_date';
        $this->aProperties['unfinished_1'] = true;
        $this->aProperties['title_attr_1'] = 'name';
        $this->aProperties['description_attr_1'] = '';
        $this->aProperties['color_1'] = $this->GetDefaultColor();

        for ($i = 2; $i <= $this->iEventResourcesCount; $i++) {
            $this->aProperties['enabled_' . $i] = false;
            $this->aProperties['query_' . $i] = 'SELECT Ticket';
            $this->aProperties['start_attr_' . $i] = 'start_date';
            $this->aProperties['end_attr_' . $i] = 'end_date';
            $this->aProperties['unfinished_' . $i] = false;
            $this->aProperties['title_attr_' . $i] = 'ref';
            $this->aProperties['description_attr_' . $i] = 'title';
            $this->aProperties['color_' . $i] = $this->GetDefaultColor();
        }
    }

    static public function GetModuleName()
    {
        return 'dashlet-calendar';
    }

    static public function GetInfo()
    {
        return array(
            'label' => Dict::S('UI:DashletCalendar:Label'),
            'icon' => 'env-' . Utils::GetCurrentEnvironment() . '/'. DashletCalendar::GetModuleName() .'/images/timetable32.png',
            'description' => Dict::S('UI:DashletCalendar:Description'),
        );
    }

    public function Render($oPage, $bEditMode = false, $aExtraParams = array())
    {
        $aResources = array();
        for ($i = 1; $i <= $this->iEventResourcesCount; $i++) {
            if (!$this->aProperties['enabled_' . $i]) continue;
            $aResources[] = array(
                'url' => $this->sUrl,
                'color' => $this->aProperties['color_' . $i],
                'data' => array(
                    'filter' => DBObjectSearch::FromOQL($this->aProperties['query_' . $i])->serialize(),
                    'start_attr' => $this->aProperties['start_attr_' . $i],
                    'end_attr' => $this->aProperties['end_attr_' . $i],
                    'unfinished' => $this->aProperties['unfinished_' . $i] ? 1 : 0,
                    'title_attr' => $this->aProperties['title_attr_' . $i],
                    'description_attr' => $this->aProperties['description_attr_' . $i],
                )
            );
        }
        $sResources = json_encode($aResources);

        $aViews = array(
            'month' => 'month',
            'week' => $this->aProperties['agenda_week'] ? 'agendaWeek' : 'basicWeek',
            'day' => $this->aProperties['agenda_day'] ? 'agendaDay' : 'basicDay',
            'list' => $this->aProperties['list_period']
        );
        $sDefaultView = $aViews[$this->aProperties['default_view']];
        $sViews = implode(', ', $aViews);

        $sDashletId = 'dashlet-calendar_' . ($bEditMode ? 'edit_' : '') . $this->sId;
        $sCalendarId = 'calendar_' . ($bEditMode ? 'edit_' : '') . $this->sId;
        $oPage->add('<div id="' . $sDashletId . '" class="dashlet-content">');
        $sHtmlTitle = htmlentities(Dict::S($this->aProperties['title']), ENT_QUOTES, 'UTF-8'); // done in the itop block
        if ($sHtmlTitle != '') {
            $oPage->add('<h1>' . $sHtmlTitle . '</h1>');
        }
        $sTimeFormat = 'H:mm'; // MetaModel::GetConfig()->Get();
        //$sDateFomat = '';
        $sLanguage = substr(strtolower(trim(UserRights::GetUserLanguage())), 0, 2);

        $oPage->add_ready_script(
            <<<EOF
                $('#$sCalendarId').fullCalendar({
        lang: '$sLanguage',
        eventSources: $sResources,
        timeFormat: '$sTimeFormat',
        header: {
            left:   'title',
            center: 'prev, today, next',
            right:  '$sViews'
        },
        defaultView: '$sDefaultView',
        eventLimit: true, // for all non-agenda views
        views: {
            agenda: {
                eventLimit: 6 // adjust to 6 only for agendaWeek/agendaDay
            }
        }
    })
EOF
        );
        $oPage->add('<div id="' . $sCalendarId . '"></div>');
        $oPage->add('</div>');
    }

    protected function GetDateAttributes($sOql)
    {
        $oQuery = $this->oModelReflection->GetQuery($sOql);
        $sClass = $oQuery->GetClass();
        $aDateAttCodes = array();
        foreach ($this->oModelReflection->ListAttributes($sClass) as $sAttCode => $sAttType) {
            if (is_subclass_of($sAttType, 'AttributeDateTime') || $sAttType == 'AttributeDateTime') {
                $sLabel = $this->oModelReflection->GetLabel($sClass, $sAttCode);
                $aDateAttCodes[$sAttCode] = $sLabel;
            }
        }
        asort($aDateAttCodes);
        return $aDateAttCodes;
    }

    protected function GetEventTextOptions($sOql)
    {
        $oQuery = $this->oModelReflection->GetQuery($sOql);
        $sClass = $oQuery->GetClass();
        $aTitleAttrs = array();
        foreach ($this->oModelReflection->ListAttributes($sClass) as $sAttCode => $sAttType) {
            if ($sAttType == 'AttributeLinkedSet') continue;
            if (is_subclass_of($sAttType, 'AttributeLinkedSet')) continue;
            if ($sAttType == 'AttributeFriendlyName') continue;
            if (is_subclass_of($sAttType, 'AttributeFriendlyName')) continue;
            if ($sAttType == 'AttributeExternalField') continue;
            if (is_subclass_of($sAttType, 'AttributeExternalField')) continue;
            if ($sAttType == 'AttributeOneWayPassword') continue;

            $sLabel = $this->oModelReflection->GetLabel($sClass, $sAttCode);
            $aTitleAttrs[$sAttCode] = $sLabel;
        }
        asort($aTitleAttrs);
        return $aTitleAttrs;
    }

    protected function GetDefaultColor() {
        $aColors = array_keys($this->GetColorOptions());
        return array_shift($aColors);
    }

    protected function GetColorOptions()
    {
        $aColorOpts = array();
        $aColors = utils::GetConfig()->GetModuleSetting($this->sModuleName, 'colors', array('grey' => 'grey'));
        foreach ($aColors as $name => $value) {
            if (is_integer($name)) {
                $name = $value;
            } elseif (!$value) {
                $value = $name;
            }
            $aColorOpts[$value] = Dict::S('UI:DashletCalendar:Event:Prop-Color:' . $name);
        }
        return $aColorOpts;
    }

    public function GetPropertiesFields(DesignerForm $oForm)
    {
        // Calendar Title
        $oField = new DesignerTextField('title', Dict::S('UI:DashletCalendar:Prop-Title'), $this->aProperties['title']);
        $oForm->AddField($oField);

        // Calendar default view
        $aViews = array(
            'month' => Dict::S('UI:DashletCalendar:Prop-Default-View:Month'),
            'week' => Dict::S('UI:DashletCalendar:Prop-Default-View:Week'),
            'day' => Dict::S('UI:DashletCalendar:Prop-Default-View:Day'),
            'list' => Dict::S('UI:DashletCalendar:Prop-Default-View:List')
        );
        $oField = new DesignerComboField('default_view', Dict::S('UI:DashletCalendar:Prop-Default-View'), $this->aProperties['default_view']);
        $oField->SetMandatory();
        $oField->SetAllowedValues($aViews);
        $oForm->AddField($oField);


        $aListViews = array(
            'listMonth' => Dict::S('UI:DashletCalendar:Prop-List-Period:Month'),
            'listWeek' => Dict::S('UI:DashletCalendar:Prop-List-Period:Week'),
            'listDay' => Dict::S('UI:DashletCalendar:Prop-List-Period:Day'),
            'listYear' => Dict::S('UI:DashletCalendar:Prop-List-Period:Year')
        );
        $oField = new DesignerComboField('list_period', Dict::S('UI:DashletCalendar:Prop-List-Period'), $this->aProperties['list_period']);
        $oField->SetMandatory();
        $oField->SetAllowedValues($aListViews);
        $oForm->AddField($oField);

        // Agenda week view
        $oField = new DesignerBooleanField('agenda_week', Dict::S('UI:DashletCalendar:Prop-Agenda-Week'), $this->aProperties['agenda_week']);
        $oForm->AddField($oField);

        // Agenda day view
        $oField = new DesignerBooleanField('agenda_day', Dict::S('UI:DashletCalendar:Prop-Agenda-Day'), $this->aProperties['agenda_day']);
        $oForm->AddField($oField);

        for ($i = 1; $i <= $this->iEventResourcesCount; $i++) {

            $oForm->StartFieldSet(Dict::Format('UI:DashletCalendar:EventSet', $i));

            // Event set enabled
            $oField = new DesignerBooleanField('enabled_' . $i, Dict::S('UI:DashletCalendar:Event:Prop-Enabled'), $this->aProperties['enabled_' . $i]);
            $oForm->AddField($oField);

            // Event query
            $oField = new DesignerLongTextField('query_' . $i, Dict::S('UI:DashletCalendar:Event:Prop-Query'), $this->aProperties['query_' . $i]);
            $oField->SetMandatory();
            $oForm->AddField($oField);

            // Event start and end dates
            try {
                // build the list of possible values (attribute codes + ...)
                $aDateAttCodes = $this->GetDateAttributes($this->aProperties['query_' . $i]);
                $oFieldStart = new DesignerComboField('start_attr_' . $i, Dict::S('UI:DashletCalendar:Event:Prop-Start'), $this->aProperties['start_attr_' . $i]);
                $oFieldStart->SetMandatory();
                $oFieldStart->SetAllowedValues($aDateAttCodes);

                $oFieldEnd = new DesignerComboField('end_attr_' . $i, Dict::S('UI:DashletCalendar:Event:Prop-End'), $this->aProperties['end_attr_' . $i]);
                $oFieldEnd->SetAllowedValues($aDateAttCodes);
            } catch (Exception $e) {
                $oFieldStart = new DesignerTextField('start_attr_' . $i, Dict::S('UI:DashletCalendar:Event:Prop-Start'), $this->aProperties['start_attr_' . $i]);
                $oFieldStart->SetReadOnly();
                $oFieldEnd = new DesignerTextField('end_attr_' . $i, Dict::S('UI:DashletCalendar:Event:Prop-End'), $this->aProperties['end_attr_' . $i]);
                $oFieldEnd->SetReadOnly();
            }
            $oForm->AddField($oFieldStart);
            $oForm->AddField($oFieldEnd);

            $oField = new DesignerBooleanField('unfinished_' . $i, Dict::S('UI:DashletCalendar:Event:Prop-Unfinished'), $this->aProperties['unfinished_' . $i]);
            $oForm->AddField($oField);

            // Event title and description
            try {
                // build the list of possible values (attribute codes + ...)
                $aAttCodes = $this->GetEventTextOptions($this->aProperties['query_' . $i]);
                $oFieldTitle = new DesignerComboField('title_attr_' . $i, Dict::S('UI:DashletCalendar:Event:Prop-Title'), $this->aProperties['title_attr_' . $i]);
                $oFieldTitle->SetMandatory();
                $oFieldTitle->SetAllowedValues($aAttCodes);

                $oFieldDescription = new DesignerComboField('description_attr_' . $i, Dict::S('UI:DashletCalendar:Event:Prop-Desc'), $this->aProperties['description_attr_' . $i]);
                $oFieldDescription->SetAllowedValues($aAttCodes);
            } catch (Exception $e) {
                $oFieldTitle = new DesignerTextField('title_attr_' . $i, Dict::S('UI:DashletCalendar:Event:Prop-Title'), $this->aProperties['title_attr_' . $i]);
                $oFieldTitle->SetReadOnly();

                $oFieldDescription = new DesignerTextField('description_attr_' . $i, Dict::S('UI:DashletCalendar:Event:Prop-Desc'), $this->aProperties['description_attr_' . $i]);
                $oFieldDescription->SetReadOnly();
            }
            $oForm->AddField($oFieldTitle);
            $oForm->AddField($oFieldDescription);

            // Event color
            $aColors = $this->GetColorOptions();
            $oField = new DesignerComboField('color_' . $i, Dict::S('UI:DashletCalendar:Event:Prop-Color'), $this->aProperties['color_' . $i]);
            $oField->SetMandatory();
            $oField->SetAllowedValues($aColors);
            $oForm->AddField($oField);
        }
    }

    public function Update($aValues, $aUpdatedFields)
    {
        for ($i = 1; $i <= $this->iEventResourcesCount; $i++) {
            if (in_array('query_' . $i, $aUpdatedFields)) {
                try {
                    $sCurrQuery = $aValues['query_' . $i];
                    $oCurrSearch = $this->oModelReflection->GetQuery($sCurrQuery);
                    $sCurrClass = $oCurrSearch->GetClass();

                    $sPrevQuery = $this->aProperties['query_' . $i];
                    $oPrevSearch = $this->oModelReflection->GetQuery($sPrevQuery);
                    $sPrevClass = $oPrevSearch->GetClass();

                    if ($sCurrClass != $sPrevClass) {
                        $this->bFormRedrawNeeded = true;
                        // wrong but not necessary - unset($aUpdatedFields['group_by']);
                        //$this->aProperties['start_attr_'.$i] = '';
                        //$this->aProperties['end_attr_'.$i] = '';
                    }
                } catch (Exception $e) {
                    $this->bFormRedrawNeeded = true;
                }
            }
        }
        $oDashlet = parent::Update($aValues, $aUpdatedFields);
        return $oDashlet;
    }
}

class DashletCalendarStaticContent implements iPageUIExtension
{
    public function GetBannerHtml(iTopWebPage $oPage)
    {
        // Workaround to use the module with Coverage Windows module that uses the same fullcalendar lib
        if (utils::ReadParam('operation', null) === null) {
            // Is it Dashboard page? We don't know. But it's definitely not Details, Create, Edit of Search page.
            $sModuleUrlBase = 'env-' . utils::GetCurrentEnvironment() . '/' . DashletCalendar::GetModuleName() . '/';
            $oPage->add_linked_stylesheet('../' . $sModuleUrlBase . 'fullcalendar/fullcalendar.min.css');
            $oPage->add_linked_stylesheet('../' . $sModuleUrlBase . 'css/custom.css');
            // $oPage->add_linked_script('../'.$sModuleUrlBase.'fullcalendar/lib/jquery.min.js'); // We use jquery from iTop source
            $oPage->add_linked_script('../' . $sModuleUrlBase . 'fullcalendar/lib/moment.min.js');
            $oPage->add_linked_script('../' . $sModuleUrlBase . 'fullcalendar/fullcalendar.min.js');
            $sLang = substr(strtolower(trim(UserRights::GetUserLanguage())), 0, 2);
            // TODO: format of iTop user's language don't match to locale file name
            if (file_exists('../' . $sModuleUrlBase . 'fullcalendar/locale/' . $sLang . '.js')) {
                $oPage->add_linked_script('../' . $sModuleUrlBase . 'fullcalendar/locale/' . $sLang . '.js');
            }
        }
        return '';
    }

    public function GetNorthPaneHtml(iTopWebPage $oPage)
    {
        return '';
    }

    public function GetSouthPaneHtml(iTopWebPage $oPage)
    {
        return '';
    }
}
