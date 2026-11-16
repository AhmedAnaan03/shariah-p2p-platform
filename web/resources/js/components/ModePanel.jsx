import BackgroundPattern from './BackgroundPattern';
import KhatamIcon from './KhatamIcon';
import { useShariahSwitch } from './ModeSwitch';
import Switch from './Switch';
import Term from './Term';

/**
 * The mode panel on the investor's home (README, "Modes"): what Shariah-only mode means, with
 * the same switch as the top bar. On the patterned band, so it reads as a setting, not content.
 * Explains Murabahah and Mudarabah on first use, and says plainly that the check is rule-based.
 * Never says "interest" or "guaranteed". Text on the band is text-secondary or darker, never
 * text-muted (which drops below 4.5:1 where a pattern line runs under it).
 */
export default function ModePanel() {
    const shariahSwitch = useShariahSwitch();

    if (!shariahSwitch) {
        return null;
    }

    const on = shariahSwitch.mode === 'shariah';

    return (
        <section aria-labelledby="mode-panel-title" className="relative overflow-hidden rounded-lg border border-border bg-band p-5 @bp-sm/shell:p-6">
            <BackgroundPattern />
            <div className="relative flex flex-col gap-4 @bp-md/shell:flex-row @bp-md/shell:items-start @bp-md/shell:justify-between">
                <div className="max-w-prose">
                    <h2 id="mode-panel-title" className="flex items-center gap-2 text-h3 text-text">
                        <KhatamIcon size={20} className="text-primary" />
                        Shariah-only mode
                    </h2>
                    <p className="mt-2 text-body text-text-secondary">
                        {on ? 'On. ' : ''}When the marketplace opens, Shariah-only mode will show you only campaigns that use{' '}
                        <Term definition="A sale at cost plus an agreed, fixed markup.">Murabahah</Term> or{' '}
                        <Term definition="Profit sharing: returns depend on the business's actual profit.">Mudarabah</Term> contracts, with
                        expected returns shown as a markup or a profit share.
                    </p>
                    <p className="mt-2 text-body-sm text-text-secondary">
                        The check is rule-based and is not a formal Shariah certification.
                    </p>
                </div>
                <div className="shrink-0 @bp-md/shell:pt-1">
                    {shariahSwitch.disabledReason ? (
                        <p className="text-body-sm text-text-secondary">{shariahSwitch.disabledReason}</p>
                    ) : (
                        <Switch
                            label="Shariah-only"
                            shariah
                            checked={on}
                            onChange={(checked) => shariahSwitch.change(checked ? 'shariah' : 'conventional')}
                        />
                    )}
                </div>
            </div>
        </section>
    );
}
