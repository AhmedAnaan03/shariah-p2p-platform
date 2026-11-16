# RiskExplanation

Why a campaign got its risk grade: a diverging bar chart of the five factors that moved the score most, each in plain words.

**Provide** `factors` (`{label, effect}`; `effect` in percentage points of the estimated missed-repayment chance, positive raises risk, negative lowers it; the first five are shown), `summary` (one sentence), `model` (version and date, shown in `code` style), `howHref` ("How is this calculated?"), `state` (`ready` | `scoring` | `pending` | `failed`), `onRetry`.

- Bars grow from a centre line: right in `chart-raise` (raises risk), left in `chart-lower` (the active primary: navy or Islamic Green). Each row repeats the direction as an arrow icon and a signed value, so it reads without colour.
- Labels say the effect: "Steady repayment history lowers risk", "Seasonal revenue raises risk".
- States: `scoring` "Scoring…" with a spinner; `pending` "Score pending, we'll update this automatically"; `failed` explains and offers "Try again".
- Bars grow over `duration-slow`, staggered 40 ms.
- Recharts: `<BarChart layout="vertical" data={factors}>` with `<XAxis type="number" domain={[-max, max]} hide />`, `<ReferenceLine x={0} />` and `<Bar dataKey="effect">` with a `<Cell fill>` per sign, `radius` 4 on the outer end.
