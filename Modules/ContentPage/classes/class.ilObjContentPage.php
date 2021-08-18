<?php declare(strict_types=1);
/* Copyright (c) 1998-2018 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\ContentPage\PageMetrics\Command\StorePageMetricsCommand;
use ILIAS\ContentPage\PageMetrics\PageMetricsRepositoryImp;
use ILIAS\ContentPage\PageMetrics\PageMetricsService;

/**
 * Class ilObjContentPage
 */
class ilObjContentPage extends ilObject2 implements ilContentPageObjectConstants
{
    protected int $styleId = 0;
    protected ?ilObjectTranslation $objTrans = null;
    private PageMetricsService $pageMetricsService;

    /**
     * @inheritDoc
     */
    public function __construct($a_id = 0, $a_reference = true)
    {
        global $DIC;

        parent::__construct($a_id, $a_reference);
        $this->initTranslationService();
        $this->initPageMetricsService($DIC->refinery());
    }

    private function initTranslationService() : void
    {
        if (null === $this->objTrans && $this->getId() > 0) {
            $this->objTrans = ilObjectTranslation::getInstance($this->getId());
        }
    }

    private function initPageMetricsService(ILIAS\Refinery\Factory $refinery) : void
    {
        $this->pageMetricsService = new PageMetricsService(
            new PageMetricsRepositoryImp($this->db),
            $refinery
        );
    }

    public function getObjectTranslation() : ilObjectTranslation
    {
        return $this->objTrans;
    }

    /**
     * @inheritdoc
     */
    protected function initType()
    {
        $this->type = self::OBJ_TYPE;
    }

    public function getStyleSheetId() : int
    {
        return $this->styleId;
    }

    /**
     * Note: A typehint cannot be be used here, because the consumer passes a string
     * @param int $styleId
     */
    public function setStyleSheetId($styleId) : void
    {
        $this->styleId = (int) $styleId;
    }

    public function writeStyleSheetId(int $styleId) : void
    {
        $this->db->manipulateF(
            'UPDATE content_object SET stylesheet = %s WHERE id = %s',
            ['integer', 'integer'],
            [$styleId, $this->getId()]
        );

        $this->setStyleSheetId($styleId);
    }

    /**
     * @inheritdoc
     */
    protected function doCloneObject($new_obj, $a_target_id, $a_copy_id = null)
    {
        /** @var self $new_obj */
        parent::doCloneObject($new_obj, $a_target_id, $a_copy_id);

        $ot = ilObjectTranslation::getInstance($this->getId());
        $ot->copy($new_obj->getId());

        if (ilContentPagePage::_exists($this->getType(), $this->getId())) {
            $translations = ilContentPagePage::lookupTranslations($this->getType(), $this->getId());
            foreach ($translations as $language) {
                $originalPageObject = new ilContentPagePage($this->getId(), 0, $language);
                $copiedXML = $originalPageObject->copyXmlContent();

                $duplicatePageObject = new ilContentPagePage();
                $duplicatePageObject->setId($new_obj->getId());
                $duplicatePageObject->setParentId($new_obj->getId());
                $duplicatePageObject->setLanguage($language);
                $duplicatePageObject->setXMLContent($copiedXML);
                $duplicatePageObject->createFromXML();

                $this->pageMetricsService->store(
                    new StorePageMetricsCommand(
                        $new_obj->getId(),
                        $duplicatePageObject->getLanguage()
                    )
                );
            }
        }

        $styleId = $this->getStyleSheetId();
        if ($styleId > 0 && !ilObjStyleSheet::_lookupStandard($styleId)) {
            $style = ilObjectFactory::getInstanceByObjId($styleId, false);
            if ($style) {
                $new_id = $style->ilClone();
                $new_obj->setStyleSheetId($new_id);
                $new_obj->update();
            }
        }

        ilContainer::_writeContainerSetting(
            $new_obj->getId(),
            ilObjectServiceSettingsGUI::INFO_TAB_VISIBILITY,
            ilContainer::_lookupContainerSetting(
                $this->getId(),
                ilObjectServiceSettingsGUI::INFO_TAB_VISIBILITY,
                true
            )
        );

        $lpSettings = new ilLPObjSettings($this->getId());
        $lpSettings->cloneSettings($new_obj->getId());
    }

    /**
     * @inheritdoc
     */
    protected function doRead()
    {
        parent::doRead();

        $this->initTranslationService();

        $res = $this->db->queryF(
            'SELECT * FROM content_page_data WHERE content_page_id = %s',
            ['integer'],
            [$this->getId()]
        );

        while ($data = $this->db->fetchAssoc($res)) {
            $this->setStyleSheetId((int) $data['stylesheet']);
        }
    }

    /**
     * @inheritdoc
     */
    protected function doCreate()
    {
        parent::doCreate();

        $this->initTranslationService();

        $this->db->manipulateF(
            'INSERT INTO content_page_data (content_page_id, stylesheet ) VALUES(%s, %s)',
            ['integer', 'integer'],
            [$this->getId(), 0]
        );
    }


    /**
     * @inheritdoc
     */
    protected function doUpdate()
    {
        parent::doUpdate();

        $this->initTranslationService();

        $trans = $this->getObjectTranslation();
        $trans->setDefaultTitle($this->getTitle());
        $trans->setDefaultDescription($this->getLongDescription());
        $trans->save();

        $this->db->manipulateF(
            'UPDATE content_page_data SET stylesheet = %s WHERE content_page_id = %s',
            ['integer', 'integer'],
            [$this->getStyleSheetId(), $this->getId()]
        );
    }

    /**
     * @inheritdoc
     */
    protected function doDelete()
    {
        parent::doDelete();

        if (ilContentPagePage::_exists($this->getType(), $this->getId())) {
            $originalPageObject = new ilContentPagePage($this->getId());
            $originalPageObject->delete();
        }

        $this->initTranslationService();
        $this->objTrans->delete();

        $this->db->manipulateF(
            'DELETE FROM content_page_metrics WHERE content_page_id = %s',
            ['integer'],
            [$this->getId()]
        );

        $this->db->manipulateF(
            'DELETE FROM content_page_data WHERE content_page_id = %s',
            ['integer'],
            [$this->getId()]
        );
    }

    /**
     * @return int[]
     */
    public function getPageObjIds() : array
    {
        $pageObjIds = [];

        $sql = 'SELECT DISTINCT page_id FROM page_object WHERE parent_id = %s AND parent_type = %s';
        $res = $this->db->queryF(
            $sql,
            ['integer', 'text'],
            [$this->getId(), $this->getType()]
        );

        while ($row = $this->db->fetchAssoc($res)) {
            $pageObjIds[] = $row['page_id'];
        }

        return $pageObjIds;
    }

    public function trackProgress(int $usrId) : void
    {
        ilChangeEvent::_recordReadEvent(
            $this->getType(),
            $this->getRefId(),
            $this->getId(),
            $usrId
        );

        ilLPStatusWrapper::_updateStatus(
            $this->getId(),
            $usrId
        );
    }
}
