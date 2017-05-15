<?php

/**
 * @author    Donatas Navidonskis <donatas@navidonskis.com>
 * @since     2017
 * @class     DataPage
 *
 * @property int    MetaPictureID
 * @property string Title
 * @property string MenuTitle
 * @property string URLSegment
 * @property string Content
 * @property string MetaDescription
 * @property string MetaKeywords
 *
 * @method Image MetaPicture
 */
class DataPage extends DataObject {

    /**
     * @var array
     * @config
     */
    private static $db = [
        'Title'           => 'Varchar(255)',
        'MenuTitle'       => 'Varchar(255)',
        'URLSegment'      => 'Varchar(318)',
        'Content'         => 'HTMLText',
        'MetaDescription' => 'Text',
        'MetaKeywords'    => 'Varchar',
    ];

    /**
     * @var array
     * @config
     */
    private static $indexes = [
        'URLSegment' => true,
    ];

    /**
     * @var array
     * @config
     */
    private static $has_one = [
        'MetaPicture' => 'Image',
    ];

    /**
     * @var array
     * @config
     */
    private static $field_labels = [
        'URLSegment' => 'URL',
    ];

    /**
     * @var string
     * @config
     */
    private static $upload_directory = 'Uploads/DataPages';

    /**
     * Get the user friendly singular name of this DataObject.
     *
     * @return string User friendly singular name of this DataObject
     */
    public function singular_name() {
        return _t('DataPage.SINGULARNAME', 'Data Page');
    }

    /**
     * Get the user friendly plural name of this DataObject
     *
     * @return string User friendly plural name of this DataObject
     */
    public function plural_name() {
        return _t('DataPage.PLURALNAME', 'Data Pages');
    }

    /**
     * @return array
     */
    public function summaryFields() {
        return [
            'Title' => $this->fieldLabel('Title'),
            'Link'  => $this->fieldLabel('URLSegment'),
        ];
    }

    /**
     * @return \FieldList
     */
    public function getCMSFields() {
        $fields = parent::getCMSFields();
        $fields->removeByName(array_keys(static::config()->db));
        $fields->findOrMakeTab('Root.SEO', $this->fieldLabel('SEO'));
        $fields->addFieldsToTab('Root.Main', [
            \TextField::create('Title', $this->fieldLabel('Title')),
            $urlSegment = \SiteTreeURLSegmentField::create('URLSegment', $this->fieldLabel('URLSegment')),
            \TextField::create('MenuTitle', $this->fieldLabel('MenuTitle')),
            \HTMLEditorField::create('Content', $this->fieldLabel('Content'))->setRows(20),
        ]);

        $fields->addFieldsToTab('Root.SEO', [
            \TextareaField::create('MetaDescription', $this->fieldLabel('MetaDescription')),
            \TextField::create('MetaKeywords', $this->fieldLabel('MetaKeywords'))->setRightTitle($this->fieldLabel('SeparateKeywordsByComma')),
            $picture = \UploadField::create('MetaPicture', $this->fieldLabel('MetaPicture')),
        ]);

        $urlSegment
            ->setURLPrefix($this->getURLPrefix())
            ->setDefaultURL($this->generateURLSegment(_t(
                'CMSMain.NEWPAGE',
                'New {pagetype}',
                ['pagetype' => $this->i18n_singular_name()]
            )));

        $picture
            ->setAllowedFileCategories('image')
            ->setAllowedMaxFileNumber(1)
            ->setRightTitle($this->fieldLabel('MetaPictureRightTitle'))
            ->setFolderName(static::config()->upload_directory);

        $this->extend('updateCMSFields', $tabbedFields);

        return $fields;
    }

    /**
     * @param string $title
     *
     * @return string
     */
    public function generateURLSegment($title) {
        $t = \URLSegmentFilter::create()->filter($title);

        // Fallback to generic page name if path is empty (= no valid, convertable characters)
        if (! $t || $t == '-' || $t == '-1') {
            $t = \URLSegmentFilter::create()->filter(
                    \FormField::name_to_label(static::class)
                )."-{$this->ID}";
        }

        // Hook for extensions
        $this->extend('updateURLSegment', $t, $title);

        return $t;
    }

    /**
     * @return string
     */
    public function getURLPrefix() {
        return \Director::baseURL();
    }

    /**
     * Return the link for this DataPage object, with the {@link DataPage::getURLPrefix()} included.
     *
     * @return string
     */
    public function Link() {
        return \Controller::join_links(
            $this->getURLPrefix(),
            $this->URLSegment
        );
    }

    /**
     * @return void
     */
    protected function onBeforeWrite() {
        parent::onBeforeWrite();

        // If there is no URLSegment set, generate one from Title
        $defaultSegment = $this->generateURLSegment(_t(
            'CMSMain.NEWPAGE',
            ['pagetype' => $this->i18n_singular_name()]
        ));

        if ((! $this->URLSegment || $this->URLSegment == $defaultSegment) && $this->Title) {
            $this->URLSegment = $this->generateURLSegment($this->Title);
        } else if ($this->isChanged('URLSegment', 2)) {
            // Do a strict check on change level, to avoid double encoding caused by
            // bogus changes through forceChange()
            $this->URLSegment = \URLSegmentFilter::create()->filter($this->URLSegment);
            // If after sanitising there is no URLSegment, give it a reasonable default
            if (! $this->URLSegment) {
                $this->URLSegment = strtolower(\FormField::name_to_label($this->class))."-{$this->ID}";
            }
        }

        // Ensure that this object has a non-conflicting URLSegment value.
        $count = 2;
        while (! $this->validURLSegment()) {
            $this->URLSegment = preg_replace('/-[0-9]+$/', null, $this->URLSegment).'-'.$count;
            $count++;
        }
    }

    /**
     * @return bool
     */
    public function validURLSegment() {
        return ! static::get()->filter(['URLSegment' => $this->URLSegment])->exclude('ID', $this->ID)->exists();
    }

    /**
     * @param bool $includeRelations
     *
     * @return array
     */
    public function fieldLabels($includeRelations = true) {
        return array_merge(parent::fieldLabels($includeRelations), [
            'Title'                   => _t('DataPage.TITLE', 'Title'),
            'MenuTitle'               => _t('DataPage.MENU_TITLE', 'Menu title'),
            'URLSegment'              => _t('DataPage.URL_SEGMENT', 'URL address'),
            'Content'                 => _t('DataPage.CONTENT', 'Content'),
            'MetaDescription'         => _t('DataPage.META_DESCRIPTION', 'Meta description'),
            'MetaKeywords'            => _t('DataPage.META_KEYWORDS', 'Meta keywords'),
            'SeparateKeywordsByComma' => _t('DataPage.SEPARATE_KEYWORDS_BY_COMMA', 'Separate keywords by comma'),
            'SEO'                     => _t('DataPage.SEO', 'SEO'),
            'MetaPicture'             => _t('DataPage.META_PICTURE', 'Meta picture'),
            'MetaPictureRightTitle'   => _t('DataPage.META_PICTURE_RIGHT_TITLE', 'Picture for social networks'),
        ]);
    }

    /**
     * @param string $value
     *
     * @return static|false
     */
    public static function getByUrlSegment($value) {
        static::get()->filter('URLSegment', $value)->first();
    }
}