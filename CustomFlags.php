<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class CustomFlags extends Module
{
    public function __construct()
    {
        $this->name = 'customFlags';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Wojciech Prusaczyk';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.8.10',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Custom flags');
        $this->description = $this->l('Module for adding custom flags to products.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('customFlags')) {
            $this->warning = $this->l('No name provided');
        }
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        $this->createTables();

        return (
            parent::install()
            && $this->registerHook('displayAdminProductsOptionsStepTop') // Dodanie flag do edycji produktu w panelu admina
            && $this->registerHook('actionObjectProductUpdateAfter') // Zapisanie wybranych flag po edycji produktu
            && $this->registerHook('actionProductFlagsModifier') // Dodanie customowych flag do wyświetlania na stronie produktu
            && Configuration::updateValue('customFlags', 'my friend')
        );
    }

    public function uninstall()
    {
        return (
            parent::uninstall()
            && Configuration::deleteByName('customFlags')
        );
    }

    /*
     * Metoda do tworzenia odpowiednich tabel w bazie danych
     */
    private function createTables()
    {
        $customFlags = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'custom_flags` (
            `flag_id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `display_text` VARCHAR(255) NOT NULL,
            `type` VARCHAR(255) NOT NULL,
            `trigger_type` VARCHAR(255) DEFAULT NULL,
            `trigger_operator` ENUM(">", "<") DEFAULT NULL,
            `trigger_value` INT(11) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`flag_id`), UNIQUE (`name`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        $productsFlags = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'products_flags` (
                `product_id` INT(11) NOT NULL,
                `flag_id` INT(11) NOT NULL
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        $db = Db::getInstance();
        return $db->execute($customFlags) && $db->execute($productsFlags);
    }

    /**
     * This method handles the module's configuration page
     * @return string The page's HTML content
     */
    public function getContent()
    {
        $output = '';

        // Dodawanie
        if (Tools::isSubmit('submit_add_flag')) {
            $output .= $this->addFlag();
        }

        // Edit
        if (Tools::isSubmit('submit_edit_flag')) {
            $output .= $this->editFlag();
        }

        // Usuwanie
        if (Tools::isSubmit('submit_delete_flag')) {
            $output .= $this->deleteFlag();
        }

        return $output . $this->displayForm();
    }

    /**
     * Builds the configuration form
     * @return string HTML code
     */
    public function displayForm()
    {
        // Generowanie listy aktualnych flag z bazy danych
        $flags = $this->getFlags();
        $flagList = [];
        if (count($flags) > 0) {
            foreach ($flags as $flag) {
                $flagList[] = [
                    'flag_id' => $flag['flag_id'],
                    'name' => $flag['name'],
                    'display_text' => $flag['display_text'],
                    'type' => $flag['type'],
                ];
            }
        }

        // Lista dostępnych flag produktu w aktualnej wersji.
        $typesList = [
            ['id' => 'online-only', 'name' => 'Online only'],
            ['id' => 'on-sale', 'name' => 'On sale'],
            ['id' => 'discount', 'name' => 'Discount'],
            ['id' => 'new', 'name' => 'New'],
            ['id' => 'pack', 'name' => 'Pack'],
            ['id' => 'out_of_stock', 'name' => 'Out of stock'],
        ];

        // Triggery
        $triggerTypes = [
            ['id' => 'none', 'name' => 'none'],
            ['id' => 'quantity', 'name' => 'Quantity']
        ];
        $triggerOperators = [
            ['id' => '>', 'name' => 'greater than'],
            ['id' => '<', 'name' => 'less than']
        ];


        $helper = new HelperForm();
        $editForm = null;
        $outputDeleteForm = null;

        // Formularz dodawania flagi
        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Add Custom Flag'),
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Flag Name'),
                        'name' => 'flag_name',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Text displayed on flag'),
                        'name' => 'flag_display_text',
                        'required' => true,
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Flag type'),
                        'name' => 'flag_type',
                        'required' => true,
                        'options' => [
                            'query' => $typesList,
                            'id' => 'id',
                            'name' => 'name',
                        ]
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Trigger type'),
                        'name' => 'flag_trigger_type',
                        'options' => [
                            'query' => $triggerTypes,
                            'id' => 'id',
                            'name' => 'name',
                        ]
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Operator'),
                        'name' => 'flag_trigger_operator',
                        'options' => [
                            'query' => $triggerOperators,
                            'id' => 'id',
                            'name' => 'name',
                        ]
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Trigger value'),
                        'name' => 'flag_trigger_value',
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Add Flag'),
                    'name' => 'submit_add_flag',
                ],
            ]
        ];

        // Formularz edycji flagi i usuwania, jeśli są dostępne flagi
        if (count($flags) > 0) {
            $editForm = [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Edit Custom Flag'),
                    ],
                    'input' => [
                        [
                            'type' => 'select',
                            'label' => $this->l('Select Flag to edit'),
                            'name' => 'edit_flag_id',
                            'options' => [
                                'query' => $flagList,
                                'id' => 'flag_id',
                                'name' => 'name',
                            ]
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Flag Name'),
                            'name' => 'edit_flag_name',
                            'required' => true,
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Text displayed on flag'),
                            'name' => 'edit_flag_display_text',
                            'required' => true,
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Flag type'),
                            'name' => 'edit_flag_type',
                            'required' => true,
                            'options' => [
                                'query' => $typesList,
                                'id' => 'id',
                                'name' => 'name',
                            ]
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Trigger type'),
                            'name' => 'edit_flag_trigger_type',
                            'options' => [
                                'query' => $triggerTypes,
                                'id' => 'id',
                                'name' => 'name',
                            ]
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Operator'),
                            'name' => 'edit_flag_trigger_operator',
                            'options' => [
                                'query' => $triggerOperators,
                                'id' => 'id',
                                'name' => 'name',
                            ]
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Trigger value'),
                            'name' => 'edit_flag_trigger_value',
                        ],
                    ],
                    'submit' => [
                        'title' => $this->l('Save Changes'),
                        'name' => 'submit_edit_flag',
                    ],
                ]
            ];

            // Formularz usuwania flagi
            $deleteForm = [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Delete Flag'),
                    ],
                    'input' => [
                        [
                            'type' => 'select',
                            'label' => $this->l('Select Flag to Delete'),
                            'name' => 'delete_flag_id',
                            'options' => [
                                'query' => $flagList,
                                'id' => 'flag_id',
                                'name' => 'name',
                            ],
                            'required' => true,
                        ],
                    ],
                    'submit' => [
                        'title' => $this->l('Delete Flag'),
                        'name' => 'submit_delete_flag',
                    ],
                ]
            ];

            // Wartości pól edycji
            $helper->fields_value['edit_flag_id'] = Tools::getValue('edit_flag_id');
            $helper->fields_value['edit_flag_name'] = Tools::getValue('edit_flag_name');
            $helper->fields_value['edit_flag_display_text'] = Tools::getValue('edit_flag_display_text');
            $helper->fields_value['edit_flag_type'] = Tools::getValue('edit_flag_type');
            $helper->fields_value['edit_flag_trigger_type'] = Tools::getValue('edit_flag_trigger_type');
            $helper->fields_value['edit_flag_trigger_operator'] = Tools::getValue('edit_flag_trigger_operator');
            $helper->fields_value['edit_flag_trigger_value'] = Tools::getValue('edit_flag_trigger_value');
            $editForm = $helper->generateForm([$editForm]);

            // Wartość pola usuwania flagi
            $helper->fields_value['delete_flag_id'] = Tools::getValue('delete_flag_id');
            $outputDeleteForm = $helper->generateForm([$deleteForm]);
        }

        // Formularz dodawania flagi
        $helper->fields_value['flag_name'] = Tools::getValue('flag_name');
        $helper->fields_value['flag_display_text'] = Tools::getValue('flag_display_text');
        $helper->fields_value['flag_type'] = Tools::getValue('flag_type');
        $helper->fields_value['flag_trigger_type'] = Tools::getValue('flag_trigger_type');
        $helper->fields_value['flag_trigger_operator'] = Tools::getValue('flag_trigger_operator');
        $helper->fields_value['flag_trigger_value'] = Tools::getValue('flag_trigger_value');
        $outputForm = $helper->generateForm([$form]);

        return $outputForm . $editForm . $outputDeleteForm;
    }


    /*
     * Hook do wyświetlania pola wyboru flagi w formularzu produktu
     */
    public function hookDisplayAdminProductsOptionsStepTop($params)
    {
        $flags = $this->getFlags();
        $selectedFlags = $this->getFlags($params['id_product']);

        $this->context->smarty->assign([
            'module' => $this,
            'flags' => $flags,
            'selectedFlags' => $selectedFlags,
            'product_id' => $params['id_product']
        ]);

        return $this->display(__FILE__, '/views/templates/admin/productFlags.tpl');
    }

    /*
     * Hook do zapisywania danych po zapisaniu formularza produktu
     */
    public function hookActionObjectProductUpdateAfter($params)
    {
        $productId = (int)$params['object']->id;
        $selectedFlags = Tools::getValue('selected_flags');

        Db::getInstance()->delete('products_flags', 'product_id = ' . $productId);

        if (is_array($selectedFlags)) {
            foreach ($selectedFlags as $flagId) {
                Db::getInstance()->insert('products_flags', [
                    'product_id' => $productId,
                    'flag_id' => (int)$flagId
                ]);
            }
        }
    }

    /*
     * Hook do wyświetlania flag na strnie produktu
     */
    public function hookActionProductFlagsModifier($params)
    {
        if (isset($params['flags']) && is_array($params['flags']) && isset($params['product']['id_product'])) {
            $productId = (int)$params['product']['id_product'];
            $flags = $this->getFlags($productId);

            if (!empty($flags)) {
                $customFlags = Db::getInstance()->executeS(
                    'SELECT name, display_text, type, trigger_type, trigger_operator, trigger_value
                 FROM `' . _DB_PREFIX_ . 'custom_flags`
                 WHERE flag_id IN (' . implode(',', array_map('intval', $flags)) . ')'
                );

                foreach ($customFlags as $flag) {

                    // Sprawdzanie warunku przyznania flagi na podstawie triggera
                    if (!is_null($flag['trigger_type']) && $flag['trigger_type'] !== 'none' && !is_null($flag['trigger_operator']) && !is_null($flag['trigger_value'])) {
                        $quantity = StockAvailable::getQuantityAvailableByProduct($productId);
                        if (($flag['trigger_operator'] === '>' && $quantity > (int)$flag['trigger_value']) ||
                            ($flag['trigger_operator'] === '<' && $quantity < (int)$flag['trigger_value'])) {
                            $params['flags'][] = [
                                'type' => $flag['type'],
                                'label' => $flag['display_text']
                            ];
                        }
                    } else {
                        // Jeśli dana flaga nie ma triggera
                        $params['flags'][] = [
                            'type' => $flag['type'],
                            'label' => $flag['display_text']
                        ];
                    }
                }
            }
        }
    }

    /*
     * Prywatna metoda do pobierania wszystkich flag, lub listy flag danego produktu
     */
    private function getFlags($productId = null): array
    {
        if ($productId) {
            $result = Db::getInstance()->executeS(
                'SELECT flag_id FROM `' . _DB_PREFIX_ . 'products_flags` WHERE product_id = ' . (int)$productId
            );
            return array_column($result, 'flag_id');
        } else {
            return Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'custom_flags`');
        }
    }

    /*
     * Prywatna metoda wywoływana po uzupełnieniu formularza dodawania flagi na stronie konfiguracji
     */
    private function addFlag()
    {
        $flagName = Tools::getValue('flag_name');
        $flagNameDuplicates = Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'custom_flags` WHERE name="' . $flagName . '"');

        $displayText = Tools::getValue('flag_display_text');

        $validFlagTypes = ['online-only', 'on-sale', 'discount', 'new', 'pack', 'out_of_stock'];
        $type = Tools::getValue('flag_type');

        $triggerType = Tools::getValue('flag_trigger_type');
        $triggerOperator = Tools::getValue('flag_trigger_operator');
        $triggerValue = Tools::getValue('flag_trigger_value');
        $triggerTypes = ['none', 'quantity'];
        $triggerOperators = [ '>', '<'];

        // Sprawdzanie poprawności danych flagi
        if (empty($flagName)) {
            $output = $this->displayError($this->l('Flag name cannot be empty.'));
        } else if (count($flagNameDuplicates) > 0) {
            $output = $this->displayError($this->l('Flag name must be unique.'));
        } else if (empty($displayText)) {
            $output = $this->displayError($this->l('Flag displayed text cannot be empty.'));
        } else if (empty($type) || !in_array($type, $validFlagTypes)) {
            $output = $this->displayError($this->l('Invalid flag type.'));
        } else if ( !empty($triggerType) && !in_array($triggerType, $triggerTypes)) {
            $output = $this->displayError($this->l('Invalid trigger type.'));
        } else if ( $triggerType !== 'none' && !empty($triggerOperator) && !in_array($triggerOperator, $triggerOperators)) {
            $output = $this->displayError($this->l('Invalid trigger operator.'));
        } else if ( $triggerType !== 'none' && (empty($triggerValue) || !is_numeric($triggerValue)) ) {
            $output = $this->displayError($this->l('Invalid trigger value.'));
        } else {
            Db::getInstance()->insert('custom_flags', [
                'name' => pSQL($flagName),
                'display_text' => pSQL($displayText),
                'type' => pSQL($type),
                'trigger_type' => pSQL($triggerType),
                'trigger_operator' => ($triggerType !== 'none')?pSQL($triggerOperator, true):'',
                'trigger_value' => ($triggerType !== 'none')?(int)$triggerValue:'',
            ]);
            $output = $this->displayConfirmation($this->l('Flag added successfully.'));
        }
        return $output;
    }

    /*
     * Prywatna metoda wywoływana po uzupełnieniu formularza edycji flagi na stronie konfiguracji
     */
    private function editFlag()
    {
        $flagId = Tools::getValue('edit_flag_id');
        $flagName = Tools::getValue('edit_flag_name');
        $flagNameDuplicates = Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'custom_flags` WHERE name="' . $flagName . '"');

        $displayText = Tools::getValue('edit_flag_display_text');

        $validFlagTypes = ['online-only', 'on-sale', 'discount', 'new', 'pack', 'out_of_stock'];
        $type = Tools::getValue('edit_flag_type');

        $triggerType = Tools::getValue('edit_flag_trigger_type');
        $triggerOperator = Tools::getValue('edit_flag_trigger_operator');
        $triggerValue = Tools::getValue('edit_flag_trigger_value');
        $triggerTypes = ['none', 'quantity'];
        $triggerOperators = [ '>', '<'];

        // Sprawdzanie poprawności danych flagi
        if (empty($flagName)) {
            $output = $this->displayError($this->l('Flag name cannot be empty.'));
        } else if (count($flagNameDuplicates) > 0) {
            $output = $this->displayError($this->l('Flag name must be unique.'));
        } else if (empty($displayText)) {
            $output = $this->displayError($this->l('Flag displayed text cannot be empty.'));
        } else if (empty($type) || !in_array($type, $validFlagTypes)) {
            $output = $this->displayError($this->l('Invalid flag type.'));
        } else if ( !empty($triggerType) && !in_array($triggerType, $triggerTypes)) {
            $output = $this->displayError($this->l('Invalid trigger type.'));
        } else if ( $triggerType !== 'none' && !empty($triggerOperator) && !in_array($triggerOperator, $triggerOperators)) {
            $output = $this->displayError($this->l('Invalid trigger operator.'));
        } else if ( $triggerType !== 'none' && (empty($triggerValue) || !is_numeric($triggerValue)) ) {
            $output = $this->displayError($this->l('Invalid trigger value.'));
        } else {
            Db::getInstance()->update('custom_flags', [
                'name' => pSQL($flagName),
                'display_text' => pSQL($displayText),
                'type' => pSQL($type),
                'trigger_type' => pSQL($triggerType),
                'trigger_operator' => ($triggerType !== 'none')?pSQL($triggerOperator, true):'',
                'trigger_value' => ($triggerType !== 'none')?(int)$triggerValue:'',
            ], 'flag_id = ' . (int)$flagId);
            return $this->displayConfirmation($this->l('Flag updated successfully.'));
        }
        return $output;
    }

    /*
     * Prywatna metoda wywoływana po uzupełnieniu formularza usiwania flagi na stronie konfiguracji
     */
    private function deleteFlag()
    {
        $flagId = Tools::getValue('delete_flag_id');

        // Sprawdzenie, czy id flagi jest prawidłowe
        if ($flagId && is_numeric($flagId)) {
            // Usuwanie flagi z bazy danych
            Db::getInstance()->delete('custom_flags', '`flag_id` = ' . (int)$flagId);
            $output = $this->displayConfirmation($this->l('Flag deleted successfully.'));
        } else {
            $output = $this->displayError($this->l('Invalid flag ID.'));
        }

        return $output;
    }
}
