<?php

if (!defined('BASEPATH'))
    die('No direct script access allowed');
/*
* LimeSurvey
* Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
* All rights reserved.
* License: GNU/GPL License v2 or later, see LICENSE.php
* LimeSurvey is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*
*/

class Survey extends LSActiveRecord
{
    /**
     * This is a static cache, it lasts only during the active request. If you ever need
     * to clear it, like on activation of a survey when in the same request a row is read,
     * saved and read again you can use resetCache() method.
     *
     * @var array
     */
    protected $findByPkCache = array();
    /* Set some setting not by default database */
    public $format = 'G';

    public function attributeLabels() {
        return [
            
            'localizedTitle' => 'Title'
        ];
    }
    /**
     * init to set default
     *
     */
    public function init()
    {
        $this->template = Template::templateNameFilter(Yii::app()->getConfig('defaulttemplate'));
        $validator= new LSYii_Validators;
        $this->language = $validator->languageFilter(Yii::app()->getConfig('defaultlang'));

        $this->attachEventHandler("onAfterFind", array($this,'fixSurveyAttribute'));
    }

    /**
     * Returns the title of the survey. Uses the current language and
     * falls back to the surveys' default language if the current language is not available.
     */
    public function getLocalizedTitle()
    {
        return $this->localizedProperty('title');
    }
    
    public function getLocalizedDescription() 
    {
        return $this->localizedProperty('description');
    }
    
    public function getLocalizedWelcomeText() 
    {
        return $this->localizedProperty('welcometext');
    }
    
    public function getLocalizedEndText() 
    {
        return $this->localizedProperty('endtext');
    }
    
    /**
     * @return string
     */
    public function getLocalizedEndUrl() {
        return $this->localizedProperty('url');
    }
    /**
     * Getter to support proper casing of the property:
     * $this->adminEmail instead of $this->adminemail
     * @return string
     */
    public function getAdminEmail() {
        return $this->attributes['adminemail'];
    }
    protected function localizedProperty($name) {
        $property = 'surveyls_' . $name;
        if (isset($this->languagesettings[App()->language])) {
            return $this->languagesettings[App()->language]->$property;
        } else {
            return $this->languagesettings[$this->language]->$property;
        }
    }
    /**
     * Expires a survey. If the object was invoked using find or new surveyId can be ommited.
     * @param int $surveyId
     */
    public function expire($surveyId = null)
    {
        $dateTime = dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig('timeadjust'));
        $dateTime = dateShift($dateTime, "Y-m-d H:i:s", '-1 day');

        if (!isset($surveyId))
        {
            $this->expires = $dateTime;
            if ($this->scenario == 'update')
            {
                return $this->save();
            }
        }
        else
        {
            self::model()->updateByPk($surveyId,array('expires' => $dateTime));
        }

    }

    /**
    * Returns the table's name
    *
    * @access public
    * @return string
    */
    public function tableName()
    {
        return '{{surveys}}';
    }

    /**
    * Returns the table's primary key
    *
    * @access public
    * @return string
    */
    public function primaryKey()
    {
        return 'sid';
    }

    /**
    * Returns the static model of Settings table
    *
    * @static
    * @access public
    * @param string $class
    * @return Survey
    */
    public static function model($class = __CLASS__)
    {
        return parent::model($class);
    }

    /**
    * Returns this model's relations
    *
    * @access public
    * @return array
    */
    public function relations()
    {
        $alias = $this->getTableAlias();
        return [
            'languagesettings' => array(self::HAS_MANY, 'SurveyLanguageSetting', 'surveyls_survey_id', 'index' => 'surveyls_language'),
            'defaultlanguage' => array(self::BELONGS_TO, 'SurveyLanguageSetting', array('language' => 'surveyls_language', 'sid' => 'surveyls_survey_id'), 'together' => true),
            'owner' => array(self::BELONGS_TO, 'User', '', 'on' => "$alias.owner_id = owner.uid"),
            
            'groups' => [self::HAS_MANY, 'QuestionGroup', 'sid']
        ];
    }

    /**
    * Returns this model's scopes
    *
    * @access public
    * @return array
    */
    public function scopes()
    {
        return array(
            'active' => array('condition' => "active = 'Y'"),
            'open' => array('condition' => '(startdate <= :now1 OR startdate IS NULL) AND (expires >= :now2 OR expires IS NULL)', 'params' => array(
                ':now1' => dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig("timeadjust")),
                ':now2' => dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig("timeadjust"))
                )
            ),
            'public' => array('condition' => "listpublic = 'Y'"),
            'registration' => array('condition' => "allowregister = 'Y' AND startdate > :now3 AND (expires < :now4 OR expires IS NULL)", 'params' => array(
                ':now3' => dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig("timeadjust")),
                ':now4' => dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig("timeadjust"))
            ))
        );
    }

    /**
    * Returns this model's validation rules
    *
    */
    public function rules()
    {
        return array(
            array('datecreated', 'default','value'=>date("Y-m-d")),
            array('startdate', 'default','value'=>NULL),
            array('expires', 'default','value'=>NULL),
            array('admin,faxto','LSYii_Validators'),
            array('adminemail','filter', 'filter'=>'trim'),
            array('bounce_email','LSYii_EmailIDNAValidator', 'allowEmpty'=>true),
            array('adminemail','filter', 'filter'=>'trim'),
            array('bounce_email','LSYii_EmailIDNAValidator', 'allowEmpty'=>true),
            array('active', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('anonymized', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('savetimings', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('datestamp', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('usecookie', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('allowregister', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('allowsave', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('autoredirect', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('allowprev', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('printanswers', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('ipaddr', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('refurl', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('publicstatistics', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('publicgraphs', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('listpublic', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('htmlemail', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('sendconfirmation', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('tokenanswerspersistence', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('assessments', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('usetokens', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('showxquestions', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('shownoanswer', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('showwelcome', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('showprogress', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('questionindex', 'numerical','min' => 0, 'max' => 2, 'allowEmpty'=>false),
            array('nokeyboard', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('alloweditaftercompletion', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
            array('bounceprocessing', 'in','range'=>array('L','N','G'), 'allowEmpty'=>true),
            array('usecaptcha', 'in','range'=>array('A','B','C','D','X','R','S','N'), 'allowEmpty'=>true),
            array('showgroupinfo', 'in','range'=>array('B','N','D','X'), 'allowEmpty'=>true),
            array('showqnumcode', 'in','range'=>array('B','N','C','X'), 'allowEmpty'=>true),
            array('format', 'in','range'=>array('G','S','A'), 'allowEmpty'=>true),
            array('googleanalyticsstyle', 'numerical', 'integerOnly'=>true, 'min'=>'0', 'max'=>'2', 'allowEmpty'=>true),
            array('autonumber_start','numerical', 'integerOnly'=>true,'allowEmpty'=>true),
            array('tokenlength','numerical', 'integerOnly'=>true,'allowEmpty'=>true, 'min'=>'5', 'max'=>'36'),
            array('bouncetime','numerical', 'integerOnly'=>true,'allowEmpty'=>true),
            array('navigationdelay','numerical', 'integerOnly'=>true,'allowEmpty'=>true),
            array('template', 'filter', 'filter'=>array($this,'filterTemplateSave')),
            array('language','LSYii_Validators','isLanguage'=>true),
            array('language', 'required', 'on' => 'insert'),
            array('language', 'filter', 'filter'=>'trim'),
            array('additional_languages', 'filter', 'filter'=>'trim'),
            array('additional_languages','LSYii_Validators','isLanguageMulti'=>true),
            // Date rules currently don't work properly with MSSQL, deactivating for now
            //  array('expires','date', 'format'=>array('yyyy-MM-dd', 'yyyy-MM-dd HH:mm', 'yyyy-MM-dd HH:mm:ss',), 'allowEmpty'=>true),
            //  array('startdate','date', 'format'=>array('yyyy-MM-dd', 'yyyy-MM-dd HH:mm', 'yyyy-MM-dd HH:mm:ss',), 'allowEmpty'=>true),
            //  array('datecreated','date', 'format'=>array('yyyy-MM-dd', 'yyyy-MM-dd HH:mm', 'yyyy-MM-dd HH:mm:ss',), 'allowEmpty'=>true),
        );
    }


    /**
    * fixSurveyAttribute to fix and/or add some survey attribute
    * - Fix template name to be sure template exist
    */
    public function fixSurveyAttribute($event)
    {
        $this->template=Template::templateNameFilter($this->template);
    }

    /**
    * filterTemplateSave to fix some template name 
    */
    public function filterTemplateSave($sTemplateName)
    {
        if(!Permission::model()->hasTemplatePermission($sTemplateName))
        {
            if(!$this->isNewRecord)// Reset to default only if different from actual value
            {
                $oSurvey=self::model()->findByPk($this->sid);
                if($oSurvey->template != $sTemplateName)// No need to test !is_null($oSurvey)
                    $sTemplateName = Yii::app()->getConfig('defaulttemplate');
            }
            else
            {
                $sTemplateName = Yii::app()->getConfig('defaulttemplate');
            }
        }
        return Template::templateNameFilter($sTemplateName);
    }

    /**
    * permission scope for this model
    *
    * @access public
    * @param int $loginID
    * @return CActiveRecord
    */
    public function permission($loginID)
    {
        $loginID = (int) $loginID;
        $criteria = $this->getDBCriteria();
        $criteria->mergeWith(array(
            'condition' => 'sid IN (SELECT entity_id FROM {{permissions}} WHERE entity = :entity AND  uid = :uid AND permission = :permission AND read_p = 1)
                            OR owner_id = :owner_id',
        ));
        $criteria->params[':uid'] = $loginID;
        $criteria->params[':permission'] = 'survey';
        $criteria->params[':owner_id'] = $loginID;
        $criteria->params[':entity'] = 'survey';

        return $this;
    }

    /**
    * Returns additional languages formatted into a string
    *
    * @access public
    * @return array
    */
    public function getAdditionalLanguages()
    {
        $sLanguages = trim($this->additional_languages);
        if ($sLanguages != '')
            return explode(' ', $sLanguages);
        else
            return array();
    }

    /**
    * Returns all languages array
    *
    * @access public
    * @return array
    */
    public function getAllLanguages()
    {
        $sLanguages = self::getAdditionalLanguages();
        $baselang=$this->language;
        array_unshift($sLanguages,$baselang);
        return $sLanguages;
    }
    
    /**
     * Returns the status for this survey.
     * Possible values are:
     * - inactive
     * - active
     * - expired
     */
    public function getStatus() {
        if (!$this->isActive) {
            $result = 'inactive';
        } elseif ($this->isExpired()) {
            $result = 'expired';
        } else {
            $result = 'active';
        }
        return $result;
    }
    public function getIsActive() {
        return $this->active != 'N';
    }
    /**
     * @return array
     */
    public function getHints() {
        $result = [];
         if (!$this->isActive && $this->questionCount == 0) {
            $result[] = gT("Survey cannot be activated yet.");
            if ($this->groupCount == 0 && App()->user->checkAccess('surveycontent', ['crud' => 'create', 'entity' => 'survey', 'entity_id' => $this->sid]))
            {
                $result[] = gT("You need to add question groups");
            }
            if ($this->questionCount == 0 && App()->user->checkAccess('surveycontent', ['crud' => 'create', 'entity' => 'survey', 'entity_id' => $this->sid]))
            {
                $result[] = gT("You need to add questions");
            }
        }
        
        if ($this->anonymized != "N") {
            $result[] = gT("Responses to this survey are anonymized.");
        } else {
            $result[] = gT("Responses to this survey are NOT anonymized.");
        }
        
        if ($this->format == "S") {
            $result[] = gT("It is presented question by question.");
        } elseif ($this->format == "G") {
            $result[] = gT("It is presented group by group.");
        } else {
            $result[] = gT("It is presented on one single page.");
        }
        
        if ($this->questionindex > 0)
        {
            if ($this->format == 'A')
            {
                $result[] = gT("No question index will be shown with this format.");
            }
            elseif ($this->questionindex == 1)
            {
                $result[] = gT("A question index will be shown; participants will be able to jump between viewed questions.");
            }
            elseif ($this->questionindex == 2)
            {
                $result[] = gT("A full question index will be shown; participants will be able to jump between relevant questions.");
            }
        }
        if ($this->datestamp == "Y")
        {
            $result[] = gT("Responses will be date stamped.");
        }
        if ($this->ipaddr == "Y")
        {
            $result[] = gT("IP Addresses will be logged");
        }
        if ($this->refurl == "Y")
        {
            $result[] = gT("Referrer URL will be saved.");
        }
        if ($this->usecookie == "Y")
        {
            $result[] = gT("It uses cookies for access control.");
        }
        if ($this->allowregister == "Y")
        {
            $result[] = gT("If tokens are used, the public may register for this survey");
        }
        if ($this->allowsave == "Y" && $this->tokenanswerspersistence == 'N')
        {
            $result[] = gT("Participants can save partially finished surveys") . "<br />\n";
        }
        if ($this->emailnotificationto != '')
        {
            $result[] = gT("Basic email notification is sent to:") .' '. htmlspecialchars($this->emailnotificationto)."<br />\n";
        }
        if ($this->emailresponseto != '')
        {
            $result[] = gT("Detailed email notification with response data is sent to:") .' '. htmlspecialchars($this->emailresponseto)."<br />\n";
        }
        
        return $result;
    }
    /**
    * Returns the additional token attributes
    *
    * @access public
    * @return array
    */
    public function getTokenAttributes()
    {
        $attdescriptiondata = decodeTokenAttributes($this->attributedescriptions);
        // checked for invalid data
        if($attdescriptiondata == null)
        {
            return array();
        }
        // Catches malformed data
        if ($attdescriptiondata && strpos(key(reset($attdescriptiondata)),'attribute_')===false)
        {
            // don't know why yet but this breaks normal tokenAttributes functionning
        }
        elseif (is_null($attdescriptiondata))
        {
            $attdescriptiondata=array();
        }
        // Legacy records support
        if ($attdescriptiondata === false)
        {
            $attdescriptiondata = explode("\n", $this->attributedescriptions);
            $fields = array();
            $languagesettings = array();
            foreach ($attdescriptiondata as $attdescription)
            {
                if (trim($attdescription) != '')
                {
                    $fieldname = substr($attdescription, 0, strpos($attdescription, '='));
                    $desc = substr($attdescription, strpos($attdescription, '=') + 1);
                    $fields[$fieldname] = array(
                    'description' => $desc,
                    'mandatory' => 'N',
                    'show_register' => 'N',
                    'cpdbmap' =>''
                    );
                    $languagesettings[$fieldname] = $desc;
                }
            }
            $ls = SurveyLanguageSetting::model()->findByAttributes(array('surveyls_survey_id' => $this->sid, 'surveyls_language' => $this->language));
            self::model()->updateByPk($this->sid, array('attributedescriptions' => json_encode($fields)));
            $ls->surveyls_attributecaptions = json_encode($languagesettings);
            $ls->save();
            $attdescriptiondata = $fields;
        }
        $aCompleteData=array();
        foreach ($attdescriptiondata as $sKey=>$aValues)
        {
            if (!is_array($aValues)) $aValues=array();
            $aCompleteData[$sKey]= array_merge(array(
                    'description' => '',
                    'mandatory' => 'N',
                    'show_register' => 'N',
                    'cpdbmap' =>''
                    ),$aValues);
        }
        return $aCompleteData;
    }

    /**
     * Returns true in a token table exists for the given $surveyId
     *
     * @staticvar array $tokens
     * @param int $iSurveyID
     * @return boolean
     */
    public function hasTokens($iSurveyID) {
        static $tokens = array();
        $iSurveyID = (int) $iSurveyID;

        if (!isset($tokens[$iSurveyID])) {
            // Make sure common_helper is loaded
            Yii::import('application.helpers.common_helper', true);

            $tokens_table = "{{tokens_{$iSurveyID}}}";
            if (tableExists($tokens_table)) {
                $tokens[$iSurveyID] = true;
            } else {
                $tokens[$iSurveyID] = false;
            }
        }

        return $tokens[$iSurveyID];
    }

	public function isExpired()
	{
		return !empty($this->expires) && $this->expires < dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", $timeadjust);
	}
    /**
    * Creates a new survey - does some basic checks of the suppplied data
    *
    * @param array $aData Array with fieldname=>fieldcontents data
    * @return integer The new survey id
    */
    public function insertNewSurvey($aData)
    {
        do
        {
            if (isset($aData['wishSID'])) // if wishSID is set check if it is not taken already
            {
                $aData['sid'] = $aData['wishSID'];
                unset($aData['wishSID']);
            }
            else
                $aData['sid'] = randomChars(6, '123456789');

            $isresult = self::model()->findByPk($aData['sid']);
        }
        while (!is_null($isresult));

        $survey = new self;
        foreach ($aData as $k => $v)
            $survey->$k = $v;
        $sResult= $survey->save();
        if (!$sResult)
        {
            tracevar($survey->getErrors());
            return false;
        }
        else return $aData['sid'];
    }

    /**
    * Deletes a survey and all its data
    *
    * @access public
    * @param int $iSurveyID
    * @param bool @recursive
    * @return void
    */
    public function deleteSurvey($iSurveyID, $recursive=true)
    {
        Survey::model()->deleteByPk($iSurveyID);

        if ($recursive == true)
        {
            if (tableExists("{{survey_".intval($iSurveyID)."}}"))  //delete the survey_$iSurveyID table
            {
                Yii::app()->db->createCommand()->dropTable("{{survey_".intval($iSurveyID)."}}");
            }

            if (tableExists("{{survey_".intval($iSurveyID)."_timings}}"))  //delete the survey_$iSurveyID_timings table
            {
                Yii::app()->db->createCommand()->dropTable("{{survey_".intval($iSurveyID)."_timings}}");
            }

            if (tableExists("{{tokens_".intval($iSurveyID)."}}")) //delete the tokens_$iSurveyID table
            {
                Yii::app()->db->createCommand()->dropTable("{{tokens_".intval($iSurveyID)."}}");
            }

            $oResult = Question::model()->findAllByAttributes(array('sid' => $iSurveyID));
            foreach ($oResult as $aRow)
            {
                Answer::model()->deleteAllByAttributes(array('qid' => $aRow['qid']));
                Condition::model()->deleteAllByAttributes(array('qid' =>$aRow['qid']));
                QuestionAttribute::model()->deleteAllByAttributes(array('qid' => $aRow['qid']));
                DefaultValue::model()->deleteAllByAttributes(array('qid' => $aRow['qid']));
            }

            Question::model()->deleteAllByAttributes(array('sid' => $iSurveyID));
            Assessment::model()->deleteAllByAttributes(array('sid' => $iSurveyID));
            QuestionGroup::model()->deleteAllByAttributes(array('sid' => $iSurveyID));
            SurveyLanguageSetting::model()->deleteAllByAttributes(array('surveyls_survey_id' => $iSurveyID));
            Permission::model()->deleteAllByAttributes(array('entity_id' => $iSurveyID, 'entity'=>'survey'));
            SavedControl::model()->deleteAllByAttributes(array('sid' => $iSurveyID));
            SurveyURLParameter::model()->deleteAllByAttributes(array('sid' => $iSurveyID));
            //Remove any survey_links to the CPDB
            SurveyLink::model()->deleteLinksBySurvey($iSurveyID);
            Quota::model()->deleteQuota(array('sid' => $iSurveyID), true);
        }
    }

    /**
     * Attribute renamed to questionindex in dbversion 169
     * Y maps to 1 otherwise 0;
     * @param type $value
     */
    public function setAllowjumps($value)
    {
        if ($value === 'Y') {
            $this->questionindex = 1;
        } else {
            $this->questionindex = 0;
        }
    }
    
    public function getInfo($language = null) {
        $language = !isset($language) ? $this->language : $language;
        if (null !== $localization = SurveyLanguageSetting::model()->findByPk(['surveyls_survey_id' => $this->primaryKey, 'surveyls_language' => $language])) {
            $result =  array_merge($this->attributes, $localization->attributes);
            $result['name']=$result['surveyls_title'];
            $result['description']=$result['surveyls_description'];
            $result['welcome']=$result['surveyls_welcometext'];
            $result['templatedir']=$result['template'];
            $result['adminname']=$result['admin'];
            $result['tablename']='{{survey_'.$result['sid'] . '}}';
            $result['urldescrip']=$result['surveyls_urldescription'];
            $result['url']=$result['surveyls_url'];
            $result['expiry']=$result['expires'];
            $result['email_invite_subj']=$result['surveyls_email_invite_subj'];
            $result['email_invite']=$result['surveyls_email_invite'];
            $result['email_remind_subj']=$result['surveyls_email_remind_subj'];
            $result['email_remind']=$result['surveyls_email_remind'];
            $result['email_confirm_subj']=$result['surveyls_email_confirm_subj'];
            $result['email_confirm']=$result['surveyls_email_confirm'];
            $result['email_register_subj']=$result['surveyls_email_register_subj'];
            $result['email_register']=$result['surveyls_email_register'];
            $result['attributedescriptions'] = $this->tokenAttributes;
            $result['attributecaptions'] = $localization->attributeCaptions;
            if (!isset($result['adminname'])) {$result['adminname']=Yii::app()->getConfig('siteadminemail');}
            if (!isset($result['adminemail'])) {$result['adminemail']=Yii::app()->getConfig('siteadminname');}
            if (!isset($result['urldescrip']) || $result['urldescrip'] == '' ) {$result['urldescrip']=$result['surveyls_url'];}

        }

        return $result;
    }
    
    /** 
     * Scope to remove surveys for which the current user doesn't have access.
     */
    public function accessible() 
    {
        if (!App()->user->checkAccess('superadmin')) {
            $this->permission(Yii::app()->user->id);
        }
        return $this;
    }
    
    public function getCompletedResponseCount() {
        return $this->isNewRecord || !Response::valid($this->sid) ? 0 : Response::model($this->sid)->complete()->count();
    }
    
    public function getPartialResponseCount() {
        return $this->isNewRecord || !Response::valid($this->sid) ? 0 : Response::model($this->sid)->incomplete()->count();
    }
    
    public function getResponseCount() {
        return $this->isNewRecord || !Response::valid($this->sid) ? 0 : Response::model($this->sid)->count();
    }
    
    /**
     * Returns the response rate of the survey as a float.
     * @todo We should decide how to define this, a good metric would be sent completed / invitation count
     * @return float
     */
    public function getResponseRate() {
        return 0;
    }
}
