<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

use ILIAS\GlobalScreen\Scope\MainMenu\Collector\Renderer\Hasher;
use ILIAS\GlobalScreen\Scope\Tool\Provider\AbstractDynamicToolProvider;
use ILIAS\GlobalScreen\ScreenContext\Stack\CalledContexts;
use ILIAS\GlobalScreen\ScreenContext\Stack\ContextCollection;
use ILIAS\UI\Implementation\Component\MainControls\Slate\Legacy as LegacySlate;

/**
 * Page  editing GS tool provider
 *
 * @author Alex Killing <killing@leifos.com>
 */
class ilCOPageEditGSToolProvider extends AbstractDynamicToolProvider
{
    use Hasher;
    public const SHOW_EDITOR = 'copg_show_editor';

    public function isInterestedInContexts(): ContextCollection
    {
        return $this->context_collection->main()->repository();
    }

    public function getToolsForContextStack(CalledContexts $called_contexts): array
    {
        $tools = [];
        $additional_data = $called_contexts->current()->getAdditionalData();
        if ($additional_data->is(self::SHOW_EDITOR, true)) {
            $title = $this->dic->language()->txt('editor');
            $icon = $this->dic->ui()->factory()->symbol()->icon()->custom(\ilUtil::getImagePath("icon_edtr.svg"), $title);

            $iff = function ($id) {
                return $this->identification_provider->contextAwareIdentifier($id);
            };
            $l = function (string $content) {
                return $this->dic->ui()->factory()->legacy($content);
            };
            $identification = $iff("copg_editor");
            $hashed = $this->hash($identification->serialize());
            $tools[] = $this->factory->tool($identification)
                ->addComponentDecorator(static function (ILIAS\UI\Component\Component $c) use ($hashed): ILIAS\UI\Component\Component {
                    if ($c instanceof LegacySlate) {
                        $signal_id = $c->getToggleSignal()->getId();
                        return $c->withAdditionalOnLoadCode(static function ($id) use ($hashed) {
                            return "
                                 $('body').on('il-copg-editor-slate', function(){
                                    il.UI.maincontrols.mainbar.engageTool('$hashed');
                                 });";
                        });
                    }
                    return $c;
                })
                ->withSymbol($icon)
                ->withTitle($title)
                ->withContent($l($this->getContent()));
        }

        return $tools;
    }

    private function getContent(): string
    {
        return "<div id='copg-editor-slate-error'></div><div id='copg-editor-slate-content'></div>";
    }
}
