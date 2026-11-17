import { Head } from '@inertiajs/react';
import { useState } from 'react';
import Alert from '../../components/Alert';
import Avatar from '../../components/Avatar';
import Badge from '../../components/Badge';
import BulletList from '../../components/BulletList';
import Button from '../../components/Button';
import Card from '../../components/Card';
import Checkbox from '../../components/Checkbox';
import ConfirmDialog from '../../components/ConfirmDialog';
import ContractBadge from '../../components/ContractBadge';
import ContractTypePicker from '../../components/ContractTypePicker';
import DescriptionList from '../../components/DescriptionList';
import Dialog from '../../components/Dialog';
import DocumentRow from '../../components/DocumentRow';
import DropdownMenu from '../../components/DropdownMenu';
import EmptyState from '../../components/EmptyState';
import FileDropzone from '../../components/FileDropzone';
import Icon from '../../components/Icon';
import Input from '../../components/Input';
import KhatamIcon from '../../components/KhatamIcon';
import ModeSwitch from '../../components/ModeSwitch';
import Money from '../../components/Money';
import MoneyInput from '../../components/MoneyInput';
import OnboardingStepper from '../../components/OnboardingStepper';
import PageBand from '../../components/PageBand';
import PageHeading from '../../components/PageHeading';
import PercentInput from '../../components/PercentInput';
import Popover from '../../components/Popover';
import RadioCards from '../../components/RadioCards';
import RadioGroup from '../../components/RadioGroup';
import RoleBadge from '../../components/RoleBadge';
import Select from '../../components/Select';
import ShariahBadge from '../../components/ShariahBadge';
import Sidebar from '../../components/Sidebar';
import SiteHeader from '../../components/SiteHeader';
import Skeleton from '../../components/Skeleton';
import Spinner from '../../components/Spinner';
import StatCard from '../../components/StatCard';
import StatusBadge from '../../components/StatusBadge';
import Switch from '../../components/Switch';
import Table from '../../components/Table';
import Tabs from '../../components/Tabs';
import Term from '../../components/Term';
import Textarea from '../../components/Textarea';
import TextInput from '../../components/TextInput';
import TextLink from '../../components/TextLink';
import { useToasts } from '../../components/Toast';
import Tooltip from '../../components/Tooltip';
import { usePlatformMode } from '../../lib/mode';

/**
 * Design preview (PB-51), at /dev/design on a developer's machine only (see routes/web.php).
 * Every shared component in every state, with a mode toggle, for checking the work against
 * docs/design-system/ and for report screenshots. The toggle here only changes the page; it
 * doesn't save anything.
 */
export default function Design() {
    const [mode, setMode] = usePlatformMode();

    return (
        <>
            <Head title="Design preview" />
            <div className="@container/shell min-h-dvh">
                <SiteHeader actions={<ModeSwitch mode={mode} onChange={setMode} label="Shariah mode" compactLabel={false} />} />
                <main className="mx-auto max-w-(--content-max) space-y-8 px-4 py-8 @bp-sm/shell:px-8">
                    <PageBand>
                        <PageHeading
                            eyebrow="PB-51 · Local only"
                            title="Design preview"
                            lead="Every shared component in every state. Switch the mode to see the whole palette cross-fade."
                        />
                    </PageBand>
                    <Typography />
                    <Buttons />
                    <Forms />
                    <Feedback />
                    <DataDisplay />
                    <Overlays />
                    <Shell mode={mode} setMode={setMode} />
                </main>
            </div>
        </>
    );
}

function Section({ id, title, description, children }) {
    return (
        <Card title={title} description={description}>
            <div id={id} className="space-y-6">
                {children}
            </div>
        </Card>
    );
}

function Row({ children }) {
    return <div className="flex flex-wrap items-center gap-3">{children}</div>;
}

function Typography() {
    return (
        <Section id="type" title="Type and figures" description="Bricolage for headings, Figtree for interface text, Plex Mono for every figure.">
            <PageHeading eyebrow="Overline eyebrow" title="Heading 1, one per page" lead="Body large: running text holds 60–75 characters a line, in plain, specific words." />
            <BulletList items={['Body text in Figtree at 15 px.', { title: 'Lead-in.', text: 'An item with a bold lead-in, as on the risk acknowledgement.' }]} />
            <Row>
                <Money value={12500} />
                <Money value={5000} decimals={0} size="figure-lg" />
                <Money value={248750.5} size="figure-xl" />
                <Money value={1200} sign />
                <Money value={-300} />
                <Money value={null} />
            </Row>
            <Row>
                <TextLink href="/dev/design">An in-text link</TextLink>
                <TextLink href="/dev/design" icon="arrow-left">
                    With an icon
                </TextLink>
                <span>
                    A term explained on first use: <Term definition="A sale at cost plus an agreed, fixed markup.">Murabahah</Term>
                </span>
            </Row>
        </Section>
    );
}

function Buttons() {
    const variants = ['primary', 'secondary', 'ghost', 'danger', 'link'];

    return (
        <Section id="buttons" title="Buttons" description="Hover, focus (Tab to see the ring), press, loading and disabled.">
            {variants.map((variant) => (
                <Row key={variant}>
                    <Button variant={variant} size="sm">
                        {variant} sm
                    </Button>
                    <Button variant={variant} iconLeft="upload-simple">
                        Upload document
                    </Button>
                    <Button variant={variant} size="lg" iconRight="arrow-right">
                        Pledge MVR 5,000
                    </Button>
                    {variant !== 'link' && (
                        <>
                            <Button variant={variant} loading>
                                Saving
                            </Button>
                            <Button variant={variant} disabled>
                                Disabled
                            </Button>
                            <Button variant={variant} icon="bell" aria-label="Notifications" />
                        </>
                    )}
                </Row>
            ))}
        </Section>
    );
}

function Forms() {
    const [text, setText] = useState('Aishath Rasheed');
    const [reason, setReason] = useState('The ID card photo is too blurred to read.');
    const [sector, setSector] = useState('');
    const [checked, setChecked] = useState(true);
    const [radio, setRadio] = useState('monthly');
    const [role, setRole] = useState('investor');
    const [on, setOn] = useState(false);

    return (
        <Section id="forms" title="Form controls" description="Label to control 8 px, between fields 20 px. Errors say what to fix.">
            <div className="grid gap-5 @bp-md/shell:grid-cols-2">
                <Input id="d-name" label="Full name" value={text} onChange={(e) => setText(e.target.value)} />
                <Input id="d-empty" label="Business name" optional placeholder="Coral Cove Trading" hint="As it appears on your registration certificate." />
                <Input id="d-error" label="Business registration number" value="C 0412 2021" onChange={() => {}} error="Enter the number in the format C-0412/2021." />
                <Input id="d-disabled" label="National ID card number" value="A123456" disabled onChange={() => {}} />
                <Input id="d-money" label="Amount" prefix="MVR" inputMode="decimal" placeholder="5,000.00" />
                <div className="flex flex-col gap-3">
                    <TextInput size="sm" icon="magnifying-glass" placeholder="Search, small" aria-label="Search" />
                    <TextInput size="lg" placeholder="Large input" aria-label="Large input" />
                </div>
                <Select
                    id="d-sector"
                    label="Sector"
                    placeholder="Choose a sector"
                    options={[
                        { value: 'fisheries', label: 'Fisheries' },
                        { value: 'tourism', label: 'Tourism and guesthouses' },
                        { value: 'retail', label: 'Retail' },
                    ]}
                    value={sector}
                    onChange={setSector}
                />
                <Select id="d-sector-error" label="Sector (error)" placeholder="Choose a sector" options={[{ value: 'a', label: 'A' }]} error="Choose your business sector." />
                <Textarea id="d-reason" label="Reason for rejecting" maxLength={255} value={reason} onChange={(e) => setReason(e.target.value)} />
                <Textarea id="d-reason-error" label="Address" value="" onChange={() => {}} error="Enter your address." />
            </div>

            <div className="grid gap-5 @bp-md/shell:grid-cols-2">
                <div className="space-y-3">
                    <Checkbox id="d-check" label="Remember me" checked={checked} onChange={setChecked} />
                    <Checkbox id="d-check-desc" label="Email me updates" description="About campaigns you have pledged to." checked={false} onChange={() => {}} />
                    <Checkbox id="d-check-ind" label="Select all" indeterminate onChange={() => {}} />
                    <Checkbox id="d-check-error" label="I understand I could lose some or all of my money" checked={false} onChange={() => {}} error="Tick the box to confirm you have read this." />
                    <Checkbox id="d-check-disabled" label="Disabled" disabled checked onChange={() => {}} />
                </div>
                <div className="space-y-5">
                    <RadioGroup
                        name="d-frequency"
                        legend="Repayment frequency"
                        value={radio}
                        onChange={setRadio}
                        options={[
                            { value: 'monthly', label: 'Monthly' },
                            { value: 'quarterly', label: 'Quarterly', description: 'Every three months.' },
                            { value: 'bullet', label: 'At the end', disabled: true },
                        ]}
                    />
                    <Switch label="Shariah-only" shariah checked={on} onChange={setOn} description="Show only Shariah-compliant campaigns." />
                    <Switch label="Disabled switch" disabled checked={false} />
                </div>
            </div>

            <RadioCards
                name="d-role"
                legend="I want to register as"
                value={role}
                onChange={setRole}
                options={[
                    { value: 'investor', label: 'Investor', icon: 'hand-coins', description: 'Fund campaigns and track your returns.' },
                    { value: 'borrower', label: 'Borrower / SME', icon: 'briefcase', description: 'Raise financing for your business.' },
                ]}
            />
            <RadioCards name="d-role-error" legend="With an error" options={[{ value: 'a', label: 'Approve' }, { value: 'b', label: 'Reject' }]} error="Choose approve or reject." />

            <div className="grid gap-5 @bp-md/shell:grid-cols-2">
                <FileDropzone id="d-file" label="File" hint="PDF, JPG or PNG. Files must be 5 MB or smaller." maxBytes={5 * 1024 * 1024} />
                <FileDropzone id="d-file-error" label="File (error)" hint="PDF, JPG or PNG." error="This file is 7.2 MB. Files must be 5 MB or smaller." />
            </div>

            <div className="grid gap-5 @bp-md/shell:grid-cols-2">
                <MoneyInput id="d-money" label="Amount needed" hint="Thousands separators appear when the box loses focus." defaultValue={120000} />
                <MoneyInput id="d-money-error" label="Amount (error)" error="The amount must be at least MVR 10,000." defaultValue={500} />
                <PercentInput id="d-percent" label="Investors’ share of the profit" hint="From 1% to 99%." value="60" />
                <PercentInput id="d-percent-error" label="Share (error)" value="100" error="The investors' share must be between 1% and 99%, so both sides share the profit." />
            </div>
            <ContractTypePicker name="d-contract" defaultValue="murabahah" />
            <ContractTypePicker name="d-contract-error" legend="Contract type (error)" error="Choose a contract type." />
        </Section>
    );
}

function Feedback() {
    const { push } = useToasts();

    return (
        <Section id="feedback" title="Feedback" description="Alerts, toasts, loading and empty states. Every tone carries an icon and words.">
            <Alert tone="info" title="Still needed for verification">
                National ID card, business registration certificate
            </Alert>
            <Alert tone="success">You have uploaded every document needed for verification.</Alert>
            <Alert tone="warning" title="Your account is pending verification" action={<Button size="sm" variant="secondary">Complete profile</Button>}>
                You can sign in and update your details, but you can't raise financing or invest yet.
            </Alert>
            <Alert tone="danger" title="Your verification was not approved" onDismiss={() => {}}>
                Reason: The ID card photo is too blurred to read.
            </Alert>
            <Row>
                {['success', 'info', 'warning', 'danger'].map((tone) => (
                    <Button key={tone} variant="secondary" size="sm" onClick={() => push({ tone, title: `A ${tone} toast`, children: 'It leaves after 5 seconds, paused on hover.' })}>
                        Show {tone} toast
                    </Button>
                ))}
            </Row>
            <Row>
                <Spinner label="Loading" />
                <Spinner size={24} />
                <div className="w-64 space-y-2">
                    <Skeleton height={16} width="70%" delay={0} />
                    <Skeleton height={12} delay={0} />
                    <Skeleton height={12} width="40%" delay={0} />
                </div>
                <Skeleton circle height={40} delay={0} />
            </Row>
            <div className="grid gap-6 @bp-md/shell:grid-cols-2">
                <EmptyState kind="queue" title="No accounts are waiting for verification" action={<Button variant="secondary">Refresh</Button>}>
                    Accounts appear here once their profile and documents are complete.
                </EmptyState>
                <EmptyState kind="campaigns" title="No campaigns yet" banded>
                    The marketplace opens in the next sprint.
                </EmptyState>
                <EmptyState kind="search" title="No results" compact>
                    Try a different word.
                </EmptyState>
                <EmptyState kind="documents" title="No documents uploaded yet" compact />
            </div>
        </Section>
    );
}

function DataDisplay() {
    const statuses = ['draft', 'pending', 'under-review', 'verified', 'live', 'funded', 'paid', 'due', 'late', 'rejected', 'failed', 'defaulted', 'pending_verification', 'suspended', 'approved', 'submitted'];
    const rows = [
        { id: 1, name: 'Aishath Rasheed', role: 'investor', registered: '12 Nov 2026', documents: 1 },
        { id: 2, name: 'Coral Cove Trading', role: 'borrower', registered: '13 Nov 2026', documents: 3 },
    ];
    const [tableRows, setTableRows] = useState(rows);

    return (
        <Section id="data" title="Data display" description="Badges, figures, lists, documents, steppers and tables.">
            <Row>
                {['neutral', 'success', 'warning', 'danger', 'info'].map((tone) => (
                    <Badge key={tone} tone={tone} icon="info">
                        {tone}
                    </Badge>
                ))}
                <Badge size="sm">Small</Badge>
            </Row>
            <Row>
                {statuses.map((status) => (
                    <StatusBadge key={status} status={status} />
                ))}
            </Row>
            <Row>
                <ContractBadge type="conventional" />
                <ContractBadge type="murabahah" />
                <ContractBadge type="mudarabah" size="sm" />
            </Row>
            <Row>
                <RoleBadge role="investor" />
                <RoleBadge role="borrower" />
                <RoleBadge role="admin" />
                <ShariahBadge />
                <ShariahBadge size="sm" />
                <Avatar name="Aishath Rasheed" />
                <Avatar name="Mohamed" size={40} />
            </Row>
            <div className="grid gap-6 @bp-md/shell:grid-cols-3">
                <StatCard label="Ready for verification" value={3} icon="user-list">
                    3 accounts have a complete profile and documents.
                </StatCard>
                <StatCard label="Wallet balance" value={12500} format="money" icon="wallet" />
                <StatCard label="Funded" value={64.2} format="pct" icon="chart-line-up" />
            </div>
            <DescriptionList
                items={[
                    ['Phone', '+960 774 2210', true],
                    ['National ID card number', 'A123456', true],
                    ['Borrowing as', 'Business (SME)'],
                    ['Sector', 'Fisheries'],
                    ['Address', 'G. Coral View, Hulhumalé'],
                    ['Website', null],
                ]}
            />
            <div className="divide-y divide-border-subtle">
                <DocumentRow title="National ID card" name="id-front.jpg" size="820 KB" uploaded="12 Nov 2026" status="pending" href="#" />
                <DocumentRow title="Business registration" name="registration.pdf" size="1.4 MB" uploaded="12 Nov 2026" status="approved" href="#" />
                <DocumentRow title="Bank statement" name="statement.png" size="2.1 MB" uploaded="11 Nov 2026" status="rejected" reason="The statement must show the last three months." href="#" />
            </div>
            <OnboardingStepper
                steps={[
                    { title: 'Complete your profile', done: true },
                    { title: 'Upload your documents', description: 'Still needed: National ID card.', href: '/dev/design', actionLabel: 'Upload documents' },
                    { title: 'Accept the risk acknowledgement', href: '/dev/design', actionLabel: 'Read and accept' },
                    { title: 'Verification by an administrator' },
                ]}
            />
            <OnboardingStepper orientation="vertical" steps={[{ title: 'Done step', done: true }, { title: 'Current step', href: '/dev/design', actionLabel: 'Go' }, { title: 'Later step' }]} />
            <Table
                caption="Sample queue"
                rows={tableRows}
                columns={[
                    { key: 'name', label: 'Name' },
                    { key: 'role', label: 'Role', render: (row) => <RoleBadge role={row.role} size="sm" /> },
                    { key: 'registered', label: 'Registered', mono: true },
                    { key: 'documents', label: 'Documents', align: 'right', mono: true },
                    {
                        key: 'remove',
                        label: <span className="sr-only">Actions</span>,
                        render: (row) => (
                            <Button size="sm" variant="secondary" onClick={() => setTableRows((list) => list.filter((r) => r.id !== row.id))}>
                                Approve
                            </Button>
                        ),
                    },
                ]}
            />
            <Button size="sm" variant="ghost" iconLeft="arrow-left" onClick={() => setTableRows(rows)}>
                Reset table
            </Button>
        </Section>
    );
}

function Overlays() {
    const [dialog, setDialog] = useState(false);
    const [confirm, setConfirm] = useState(false);
    const [pledge, setPledge] = useState(false);

    return (
        <Section id="overlays" title="Overlays and navigation" description="Dialogs, menus, popovers, tooltips and tabs, all on Radix primitives.">
            <Row>
                <Button variant="secondary" onClick={() => setDialog(true)}>
                    Open dialog
                </Button>
                <Button variant="danger" onClick={() => setConfirm(true)}>
                    Reject account
                </Button>
                <Button onClick={() => setPledge(true)}>Pledge MVR 5,000</Button>
                <DropdownMenu
                    trigger={<Button variant="secondary" iconRight="caret-down">Menu</Button>}
                    items={[
                        { heading: 'Signed in as aishath@example.com' },
                        { icon: 'key', label: 'Change password' },
                        { icon: 'eye', label: 'Disabled item', disabled: true },
                        '-',
                        { icon: 'sign-out', label: 'Sign out', danger: true },
                    ]}
                />
                <Popover trigger={<Button variant="secondary" iconLeft="bell">Popover</Button>}>
                    <EmptyState kind="notifications" title="No notifications yet" compact />
                </Popover>
                <Tooltip content="A short hint on hover or focus">
                    <Button variant="ghost" icon="question" aria-label="Help" />
                </Tooltip>
            </Row>
            <Tabs tabs={[{ value: 'pending', label: 'Pending', count: 3 }, { value: 'verified', label: 'Verified', count: 12 }, { value: 'rejected', label: 'Rejected' }]} aria-label="Sample tabs">
                {(active) => <p>Showing the {active} tab.</p>}
            </Tabs>

            <Dialog open={dialog} onClose={() => setDialog(false)} title="A dialog title" description="Modal titles use the h4 style." footer={<Button onClick={() => setDialog(false)}>Done</Button>}>
                <p>Focus is trapped here; Escape closes it and focus returns to the button.</p>
            </Dialog>
            <ConfirmDialog
                open={confirm}
                onClose={() => setConfirm(false)}
                onConfirm={() => setConfirm(false)}
                tone="danger"
                title="Reject Aishath Rasheed?"
                consequence="Aishath will be told the account was not approved, with your reason."
                rows={[['Reason', 'The ID card photo is too blurred to read.']]}
                confirmLabel="Reject and notify user"
            />
            <ConfirmDialog
                open={pledge}
                onClose={() => setPledge(false)}
                onConfirm={() => setPledge(false)}
                title="Confirm your pledge"
                amount={5000}
                rows={[['Campaign', 'Coral Cove Trading'], ['Contract', 'Murabahah'], ['Expected return', '9.5% markup']]}
                consequence="You could lose some or all of your money."
                simulated
                ack="I have read the campaign's risks."
                confirmLabel="Confirm pledge of MVR 5,000.00"
            />
        </Section>
    );
}

function Shell({ mode, setMode }) {
    const groups = [
        {
            items: [
                { href: '/dev/design', label: 'Overview', icon: 'house', active: true },
                { href: '/dev/design#p', label: 'Profile', icon: 'user' },
                { href: '/dev/design#d', label: 'Documents', icon: 'file-text', count: 2 },
            ],
        },
    ];

    return (
        <Section id="shell" title="App shell and patterns" description="Sidebar expanded and collapsed, the mode switch, and both background textures.">
            <div className="grid gap-6 @bp-md/shell:grid-cols-[auto_auto_1fr]">
                <div className="h-96 w-(--sidebar) overflow-hidden rounded-lg border border-border">
                    <Sidebar portal="Investor" groups={groups} collapsible={false} />
                </div>
                <div className="h-96 w-(--sidebar-collapsed) overflow-hidden rounded-lg border border-border">
                    <Sidebar portal="Investor" groups={groups} collapsed />
                </div>
                <div className="space-y-4">
                    <Row>
                        <ModeSwitch mode={mode} onChange={setMode} compactLabel={false} />
                        <ModeSwitch mode="conventional" compactLabel={false} disabledReason="Complete your profile to choose Shariah-only mode." />
                    </Row>
                    <Row>
                        <KhatamIcon />
                        <KhatamIcon size={24} className="text-primary" />
                        <Icon name="bell" />
                        <Icon name="bell" weight="bold" className="text-primary" />
                    </Row>
                    <PageBand>
                        <p>The page band: color-band behind the current mode's pattern.</p>
                    </PageBand>
                </div>
            </div>
        </Section>
    );
}
