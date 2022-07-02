<?php

namespace Outl1ne\MenuBuilder;

use Illuminate\Http\Request;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;
use Illuminate\Support\Str;
use Laravel\Nova\Menu\MenuSection;

class MenuBuilder extends Tool
{
    public function boot()
    {
        Nova::script('nova-menu', __DIR__ . '/../dist/entry.js');

        $menuBuilderUriKey = static::getMenuResource()::uriKey();
        Nova::provideToScript([
            'menuBuilderUriKey' => $menuBuilderUriKey,
        ]);
    }

    public function menu(Request $request)
    {
        // Outl1ne\MenuBuilder\MenuBuilder::getMenuResource()::authorizedToViewAny(request())
        return MenuSection::make(__('novaMenuBuilder.sidebarTitle'))
            ->path('/menus')
            ->icon(config('nova-menu.icon','adjustments'));
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public static function getLocales(): array
    {
        $localesConfig = config('nova-menu.locales');

        if (is_callable($localesConfig)) {
            return call_user_func($localesConfig);
        } elseif (is_array($localesConfig)) {
            return $localesConfig;
        } elseif (Str::contains($localesConfig, '@')) {
            [$class, $method] = Str::parseCallback($localesConfig);
            return app()->make($class)->{$method}();
        }

        return ['en' => 'English'];
    }

    public static function getFieldsFromMenuItemTypeClass(string $menuItemTypeClass): array
    {
        $templateFields = [];

        $handleField = function (&$field) {
            if (!empty($field->attribute) && ($field->attribute !== 'ComputedField')) {
                if (empty($field->panel)) {
                    $field->attribute = 'data->' . $field->attribute;
                } else {
                    $sanitizedPanel = nova_menu_builder_sanitize_panel_name($field->panel);
                    $field->attribute = 'data->' . $sanitizedPanel . '->' . $field->attribute;
                }
            }

            return $field;
        };

        if (isset($menuItemTypeClass) && method_exists($menuItemTypeClass, 'getFields')) {
            $rawFields = $menuItemTypeClass::getFields();
            foreach ($rawFields as $field) {
                // Handle Panel
                if ($field instanceof \Laravel\Nova\Panel) {
                    array_map(
                        function ($_field) use ($handleField, &$templateFields) {
                            $templateFields[] = $handleField($_field);
                        },
                        $field->data
                    );
                    continue;
                }

                // Handle Field
                $templateFields[] = $handleField($field);
            }
        }

        return $templateFields;
    }

    public static function getRulesFromMenuLinkable(?string $menuLinkableClass)
    {
        $menusTableName = MenuBuilder::getMenusTableName();

        $menuItemRules = $menuLinkableClass ? $menuLinkableClass::getRules() : [];
        $dataRules = [];
        foreach ($menuItemRules as $key => $rule) {
            if ($key !== 'value' && !Str::startsWith($key, 'data->')) $key = "data->{$key}";
            $dataRules[$key] = $rule;
        }

        return array_merge([
            'menu_id' => "required|exists:$menusTableName,id",
            'name' => 'required|min:1',
            'locale' => 'required',
            'value' => 'present',
            'class' => 'required',
            'target' => 'required|in:_self,_blank'
        ], $dataRules);
    }



    // In-package helpers
    public static function getMenuResource()
    {
        return config('nova-menu.resource', \Outl1ne\MenuBuilder\Nova\Resources\MenuResource::class);
    }

    public static function getMenusTableName()
    {
        return config('nova-menu.menus_table_name', 'nova_menu_menus');
    }

    public static function getMenuItemsTableName()
    {
        return config('nova-menu.menu_items_table_name', 'nova_menu_menu_items');
    }

    public static function getMenuClass()
    {
        return config('nova-menu.menu_model', \Outl1ne\MenuBuilder\Models\Menu::class);
    }

    public static function getMenuItemClass()
    {
        return config('nova-menu.menu_item_model', \Outl1ne\MenuBuilder\Models\MenuItem::class);
    }

    public static function getMenuItemTypes()
    {
        return config('nova-menu.menu_item_types', []);
    }

    public static function getMenus()
    {
        return config('nova-menu.menus', []);
    }

    public static function getMenuConfig($slug)
    {
        return config("nova-menu.menus.{$slug}", []);
    }

    public static function showDuplicate()
    {
        return config("nova-menu.show_duplicate", true);
    }

    public static function collapsedAsDefault()
    {
        return config("nova-menu.collapsed_as_default", true);
    }
}
