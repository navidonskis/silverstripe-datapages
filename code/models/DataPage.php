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
 * @property string CanViewType
 *
 * @method Image MetaPicture
 * @method DataList ViewerGroups
 */
class DataPage extends DataObject implements CMSPreviewable, PermissionProvider {

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
        'MetaKeywords'    => 'Varchar(255)',
        "CanViewType"     => "Enum('Anyone, LoggedInUsers, OnlyTheseUsers, Inherit', 'Inherit')",
    ];

    /**
     * @var array
     * @config
     */
    private static $defaults = [
        "CanViewType" => "Inherit",
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
    private static $many_many = [
        'ViewerGroups' => 'Group',
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
     * Limit content words when collecting meta tags
     *
     * @var int
     * @config
     */
    private static $limit_word_count = 20;

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
            'Title'   => $this->fieldLabel('Title'),
            'Link'    => $this->fieldLabel('URLSegment'),
            'Summary' => $this->fieldLabel('Content'),
        ];
    }

    /**
     * @return \FieldList
     */
    public function getCMSFields() {
        $fields = parent::getCMSFields();
        $fields->removeByName(array_merge(array_keys(static::config()->db), [
            'ViewerGroups',
        ]));

        $fields->findOrMakeTab('Root.Settings', $this->fieldLabel('Settings'));
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

        $fields->addFieldsToTab('Root.Settings', [
            $viewersOptionsField = new OptionsetField(
                "CanViewType",
                _t('SiteTree.ACCESSHEADER', "Who can view this page?")
            ),
            $viewerGroupsField = ListboxField::create("ViewerGroups", _t('SiteTree.VIEWERGROUPS', "Viewer Groups")),
        ]);

        $viewersOptionsSource = [];
        $viewersOptionsSource["Inherit"] = _t('SiteTree.INHERIT', "Inherit from parent page");
        $viewersOptionsSource["Anyone"] = _t('SiteTree.ACCESSANYONE', "Anyone");
        $viewersOptionsSource["LoggedInUsers"] = _t('SiteTree.ACCESSLOGGEDIN', "Logged-in users");
        $viewersOptionsSource["OnlyTheseUsers"] = _t('SiteTree.ACCESSONLYTHESE', "Only these people (choose from list)");
        $viewersOptionsField->setSource($viewersOptionsSource);

        $viewerGroupsField
            ->setMultiple(true)
            ->setSource($this->getMappedGroups())
            ->setAttribute(
                'data-placeholder',
                _t('SiteTree.GroupPlaceholder', 'Click to select group')
            );


        if (class_exists('DisplayLogicFormField')) {
            $viewerGroupsField->displayIf('CanViewType')->isEqualTo('OnlyTheseUsers');
        }

        if (! Permission::check('SITETREE_GRANT_ACCESS')) {
            $fields->makeFieldReadonly($viewersOptionsField);
            if ($this->CanViewType == 'OnlyTheseUsers') {
                $fields->makeFieldReadonly($viewerGroupsField);
            } else {
                $fields->removeByName('ViewerGroups');
            }
        }

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
     * Set your cms edit link by overriding this method.
     *
     * @return string|false
     */
    public function CMSEditLink() {
        return false;
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
            'Settings'                => _t('DataPage.SETTINGS', 'Settings'),
        ]);
    }

    /**
     * @param string $value
     *
     * @return static|false
     */
    public static function getByUrlSegment($value) {
        return static::get()->filter('URLSegment', $value)->first();
    }

    /**
     * @param bool $includeTitle
     *
     * @return string
     */
    public function MetaTags($includeTitle = true) {
        $tags = "";

        if ($includeTitle === true || $includeTitle == 'true') {
            $tags .= "<title>".Convert::raw2xml($this->Title)."</title>\n";
        }

        $charset = Config::inst()->get('ContentNegotiator', 'encoding');
        $tags .= "<meta http-equiv=\"Content-type\" content=\"text/html; charset=$charset\" />\n";

        if ($description = $this->getSummary()) {
            $description = Convert::raw2att($description);
            $tags .= "<meta name=\"description\" content=\"{$description}\" />\n";
        }

        if ($this->MetaKeywords) {
            $tags .= "<meta name=\"keywords\" content=\"{$this->MetaKeywords}\" />\n";
        }

        if ($picture = $this->getMetaImage()) {
            $contentType = mime_content_type($picture->getFullPath());

            $tags .= "<meta property=\"og:image\" content=\"{$picture->getAbsoluteURL()}\" />\n";
            $tags .= "<meta property=\"og:image:type\" content=\"{$contentType}\" />\n";
            $tags .= "<meta property=\"og:image:width\" content=\"{$picture->getWidth()}\" />\n";
            $tags .= "<meta property=\"og:image:height\" content=\"{$picture->getHeight()}\" />\n";
        }

        $this->extend('MetaTags', $tags);

        return $tags;
    }

    /**
     * Get meta picture. If you have some other image for your object,
     * override this method to return one. Crop images which are greater than 1024px,
     * for the performance and the quality. Facebook are happy with that quality images.
     *
     * @return Image|false
     */
    public function getMetaImage() {
        if (($picture = $this->MetaPicture()) && $picture->exists()) {
            if ($picture->getWidth() > 1024) {
                return $picture->Fill(1024, 1024);
            }

            return $picture;
        }

        return false;
    }

    /**
     * Get description for meta tags. First checking if MetaDescription field are filled,
     * otherwise checking the Content field and cutting by limiting word count.
     *
     * @return string
     */
    public function getSummary() {
        if (empty($this->MetaDescription) && ! empty($this->Content)) {
            $content = Convert::raw2att($this->Content);
            /** @var Varchar $content */
            $content = DBField::create_field('Varchar', $content);
            $content = $content->LimitWordCount(static::config()->limit_word_count);
            $content = strip_tags(preg_replace('/<[^>]*>/', '', str_replace(["&nbsp;", "\n", "\r"], "", html_entity_decode($content, ENT_QUOTES, 'UTF-8'))));

            return $content;
        }

        return $this->MetaDescription;
    }

    /**
     * Set a page or parent object and override this method to be display
     * for users were permissions are required.
     *
     * @return bool
     */
    public function canViewParent() {
        return true;
    }

    public function canView($member = null) {
        if (! $member || ! (is_a($member, 'Member')) || is_numeric($member)) {
            $member = Member::currentUserID();
        }

        // admin override
        if ($member && Permission::checkMember($member, ['ADMIN', 'SITETREE_VIEW_ALL'])) {
            return true;
        }

        // Standard mechanism for accepting permission changes from extensions
        $extended = $this->extendedCan('canView', $member);

        if ($extended !== null) {
            return $extended;
        }

        if ($this->CanViewType == 'Inherit') {
            return $this->canViewParent();
        }

        // check for empty spec
        if (! $this->CanViewType || $this->CanViewType == 'Anyone') {
            return true;
        }

        // check for any logged-in users
        if ($this->CanViewType == 'LoggedInUsers' && $member) {
            return true;
        }

        // check for specific groups
        if ($member && is_numeric($member)) {
            $member = DataObject::get_by_id('Member', $member);
        }
        if ($this->CanViewType == 'OnlyTheseUsers' && $member && $member->inGroups($this->ViewerGroups())) {
            return true;
        }

        return false;
    }

    public function canEdit($member = null) {
        return Permission::check('ADMIN') || Permission::check('DATAPAGE_EDIT');
    }

    public function canDelete($member = null) {
        return Permission::check('ADMIN') || Permission::check('DATAPAGE_DELETE');
    }

    public function canCreate($member = null) {
        return Permission::check('ADMIN') || Permission::check('DATAPAGE_CREATE');
    }

    public function canPublish($member = null) {
        return Permission::check('ADMIN') || Permission::check('DATAPAGE_PUBLISH');
    }

    public function providePermissions() {
        return [
            'DATAPAGE_EDIT'    => [
                'name'     => _t('DataPage.EDIT_DATA_PAGE', 'Edit data page'),
                'category' => _t('DataPage.PERMISSION_CATEGORY', 'Data pages'),
            ],
            'DATAPAGE_DELETE'  => [
                'name'     => _t('DataPage.DELETE_DATA_PAGE', 'Delete data page'),
                'category' => _t('DataPage.PERMISSION_CATEGORY', 'Data pages'),
            ],
            'DATAPAGE_CREATE'  => [
                'name'     => _t('DataPage.CREATE_DATA_PAGE', 'Create data page'),
                'category' => _t('DataPage.PERMISSION_CATEGORY', 'Data pages'),
            ],
            'DATAPAGE_PUBLISH' => [
                'name'     => _t('DataPage.PUBLISH_DATA_PAGE', 'Publish data page'),
                'category' => _t('DataPage.PERMISSION_CATEGORY', 'Data pages'),
            ],
        ];
    }

    /**
     * @return array
     */
    protected function getMappedGroups() {
        $groupsMap = [];

        foreach (Group::get() as $group) {
            $groupsMap[$group->ID] = $group->getBreadcrumbs(' > ');
        }

        asort($groupsMap);

        return $groupsMap;
    }
}