import Alert from './Alert';

/**
 * Tells a borrower what investors will see about them on the campaign page (PB-17), on the
 * onboarding finance step and the financing request form. The wording comes from the server
 * (App\Support\InvestorDisclosure), so both pages say exactly the same thing.
 *
 *   <DisclosureNotice notice={investorDisclosure} />   // { title, body: [paragraph, ...] }
 */
export default function DisclosureNotice({ notice }) {
    if (!notice) {
        return null;
    }

    return (
        <Alert tone="info" role="note" title={notice.title}>
            {notice.body.map((paragraph) => (
                <p key={paragraph}>{paragraph}</p>
            ))}
        </Alert>
    );
}
