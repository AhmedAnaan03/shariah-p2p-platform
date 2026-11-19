/* Component API reference from the Amanah P2P design system (Claude Design).
   It describes the props of every designed component. Build the real components in
   web/resources/js/components to match these names and props. This file is a reference only:
   don't import it, and don't add the design tool's window.Amanah globals to the app. */
import type * as React from 'react';

type Node = React.ReactNode;
export type Mode = 'conventional' | 'shariah';
export type Tone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';
export type Contract = 'conventional' | 'murabahah' | 'mudarabah';
export type Grade = 'A' | 'B' | 'C' | 'D' | 'E';
export type Status = 'draft' | 'pending' | 'under-review' | 'verified' | 'live' | 'funded' | 'paid' | 'due' | 'late' | 'rejected' | 'failed' | 'defaulted';

/* Foundations */
export interface ModeScopeProps { mode?: Mode; appearance?: 'light' | 'dark'; as?: keyof JSX.IntrinsicElements; className?: string; style?: React.CSSProperties; children?: Node }
export declare function ModeScope(props: ModeScopeProps): React.ReactElement;
export interface IconProps { name: string; size?: 16 | 20 | 24 | number; weight?: 'regular' | 'bold'; label?: string; className?: string }
export declare function Icon(props: IconProps): React.ReactElement;
export interface MoneyProps { value: number; size?: 'figure' | 'figure-lg' | 'figure-xl'; code?: string; decimals?: number; sign?: boolean; className?: string }
export declare function Money(props: MoneyProps): React.ReactElement;
export declare function KhatamIcon(props: { size?: number; className?: string }): React.ReactElement;
export declare function MotifTexture(props: { size?: number; className?: string }): React.ReactElement;
export declare function WaveTexture(props: { size?: number; className?: string }): React.ReactElement;
export interface BackgroundPatternProps { fade?: boolean | 'down'; className?: string }
export declare function BackgroundPattern(props: BackgroundPatternProps): React.ReactElement;
/** Platform mode state: starts from `initial` or the page theme, follows later page-theme changes. */
export declare function usePlatformMode(initial?: Mode): [Mode, (m: Mode) => void];

/* Actions */
export interface ButtonProps extends Omit<React.ButtonHTMLAttributes<HTMLButtonElement>, 'children'> { variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'link'; size?: 'sm' | 'md' | 'lg'; iconLeft?: string; iconRight?: string; icon?: string; loading?: boolean; children?: Node }
export declare function Button(props: ButtonProps): React.ReactElement;
export interface ButtonGroupProps { options: (string | { value: string; label: Node; icon?: string })[]; value?: string; onChange?: (v: string) => void; size?: 'sm' | 'md'; 'aria-label': string }
export declare function ButtonGroup(props: ButtonGroupProps): React.ReactElement;
export interface SplitButtonProps { presets?: number[]; value?: number; onChange?: (v: number) => void; onAction?: (amount: number) => void; disabled?: boolean; label?: (amount: number) => string }
export declare function SplitButton(props: SplitButtonProps): React.ReactElement;
export interface SpinnerProps { size?: number; label?: string }
export declare function Spinner(props: SpinnerProps): React.ReactElement;

/* Forms */
export interface FieldProps { label?: Node; htmlFor?: string; required?: boolean; optional?: boolean; helper?: Node; error?: Node; children: Node }
export declare function Field(props: FieldProps): React.ReactElement;
export interface TextInputProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'size' | 'prefix'> { size?: 'sm' | 'md' | 'lg'; prefix?: Node; suffix?: Node; icon?: string; invalid?: boolean; inputClassName?: string }
export declare function TextInput(props: TextInputProps): React.ReactElement;
export interface TextareaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> { invalid?: boolean; maxLength?: number }
export declare function Textarea(props: TextareaProps): React.ReactElement;
export interface SelectProps { options: { value: string; label: Node }[]; value?: string; defaultValue?: string; onChange?: (v: string) => void; placeholder?: string; size?: 'sm' | 'md' | 'lg'; disabled?: boolean; invalid?: boolean }
export declare function Select(props: SelectProps): React.ReactElement;
export interface ComboboxProps { options: { value: string; label: string; icon?: string }[]; defaultValue?: string; placeholder?: string; emptyText?: string; defaultOpen?: boolean }
export declare function Combobox(props: ComboboxProps): React.ReactElement;
export interface CheckboxProps { label?: Node; description?: Node; checked?: boolean; defaultChecked?: boolean; indeterminate?: boolean; disabled?: boolean; invalid?: boolean; onChange?: (checked: boolean) => void }
export declare function Checkbox(props: CheckboxProps): React.ReactElement;
export interface RadioGroupProps { options: { value: string; label: Node; description?: Node; disabled?: boolean }[]; value?: string; defaultValue?: string; onChange?: (v: string) => void; orientation?: 'vertical' | 'horizontal'; disabled?: boolean; name?: string; 'aria-label'?: string }
export declare function RadioGroup(props: RadioGroupProps): React.ReactElement;
export interface SwitchProps { label?: Node; description?: Node; checked?: boolean; defaultChecked?: boolean; onChange?: (on: boolean) => void; disabled?: boolean; shariah?: boolean }
export declare function Switch(props: SwitchProps): React.ReactElement;
export interface MoneyInputProps extends Omit<TextInputProps, 'value' | 'defaultValue'> { value?: number | string; defaultValue?: number | string; onValueChange?: (amount: number) => void }
export declare function MoneyInput(props: MoneyInputProps): React.ReactElement;
export declare function PercentInput(props: TextInputProps): React.ReactElement;
export declare function PhoneInput(props: TextInputProps): React.ReactElement;
export declare function NationalIdInput(props: TextInputProps): React.ReactElement;
export interface DatePickerProps { defaultValue?: string; min?: string; defaultOpen?: boolean; placeholder?: string }
export declare function DatePicker(props: DatePickerProps): React.ReactElement;
export interface FileDropzoneProps { files?: { name: string; size: number; progress: number; error?: string }[]; accept?: string; maxBytes?: number; hint?: string; disabled?: boolean; error?: Node }
export declare function FileDropzone(props: FileDropzoneProps): React.ReactElement;
export interface RadioCardsProps { options: { value: string; label: Node; description?: Node; icon?: string; badge?: Node; disabled?: boolean; wrap?: (card: React.ReactElement) => React.ReactElement }[]; value?: string; defaultValue?: string; onChange?: (v: string) => void; columns?: number; 'aria-label'?: string }
export declare function RadioCards(props: RadioCardsProps): React.ReactElement;
export declare function RolePicker(props: { defaultValue?: 'investor' | 'borrower' }): React.ReactElement;
export declare function ContractTypePicker(props: { defaultValue?: Contract; value?: Contract; onChange?: (c: Contract) => void }): React.ReactElement;

/* Feedback */
export interface AlertProps { tone?: Exclude<Tone, 'neutral'>; title?: Node; children?: Node; action?: Node; onDismiss?: () => void }
export declare function Alert(props: AlertProps): React.ReactElement;
export interface ToastProps { tone?: Tone; title: Node; children?: Node; action?: Node; onClose?: () => void; duration?: number }
export declare function Toast(props: ToastProps): React.ReactElement;
export declare function Toaster(props: { toasts: (ToastProps & { id: string | number })[]; onDismiss: (id: string | number) => void; inline?: boolean }): React.ReactElement;
export declare function useToasts(): { toasts: (ToastProps & { id: string })[]; push: (t: ToastProps) => void; dismiss: (id: string) => void };
export interface EmptyStateProps { kind?: 'campaigns' | 'documents' | 'queue' | 'search'; title: Node; children?: Node; action?: Node; banded?: boolean; compact?: boolean }
export declare function EmptyState(props: EmptyStateProps): React.ReactElement;
export interface SkeletonProps { width?: number | string; height?: number; radius?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'; circle?: boolean; delay?: number }
export declare function Skeleton(props: SkeletonProps): React.ReactElement;
export interface DialogProps { open: boolean; onClose?: () => void; title: Node; description?: Node; children?: Node; footer?: Node; size?: 'sm' | 'md' | 'lg' }
export declare function Dialog(props: DialogProps): React.ReactElement | null;
export interface ConfirmDialogProps { open: boolean; onClose: () => void; onConfirm: () => void; title: Node; amount?: number; rows?: [Node, Node][]; consequence?: Node; confirmLabel?: string; tone?: 'primary' | 'danger'; loading?: boolean; simulated?: boolean; ack?: Node }
export declare function ConfirmDialog(props: ConfirmDialogProps): React.ReactElement | null;
export interface DrawerProps { open: boolean; onClose: () => void; title?: Node; side?: 'left' | 'right'; width?: number; children?: Node; footer?: Node }
export declare function Drawer(props: DrawerProps): React.ReactElement | null;

/* Data display */
export interface BadgeProps { tone?: Tone; icon?: string; size?: 'sm' | 'md'; children?: Node }
export declare function Badge(props: BadgeProps): React.ReactElement;
export declare function StatusBadge(props: { status: Status; size?: 'sm' | 'md'; children?: Node }): React.ReactElement;
export declare function ShariahBadge(props: { size?: 'sm' | 'md'; label?: string }): React.ReactElement;
export declare function ContractBadge(props: { type: Contract; size?: 'sm' | 'md' }): React.ReactElement;
export declare function AIScoredBadge(props: { size?: 'sm' | 'md' }): React.ReactElement;
export declare function RoleBadge(props: { role: 'investor' | 'borrower' | 'admin'; size?: 'sm' | 'md' }): React.ReactElement;
export interface RiskGradeProps { grade: Grade; variant?: 'solid' | 'tinted'; size?: 'sm' | 'md' | 'lg'; tooltip?: boolean }
export declare function RiskGrade(props: RiskGradeProps): React.ReactElement;
export interface RiskGradeCardProps { grade?: Grade; probability?: number; sentence?: Node; model?: string; state?: 'ready' | 'scoring' | 'pending' }
export declare function RiskGradeCard(props: RiskGradeCardProps): React.ReactElement;
export interface ProgressBarProps { value: number; max?: number; label: string; size?: 'sm' | 'md' | 'lg'; showValue?: boolean; tone?: Tone }
export declare function ProgressBar(props: ProgressBarProps): React.ReactElement;
export interface StatCardProps { label: Node; value: number; format?: 'money' | 'pct' | 'count'; delta?: number; deltaLabel?: Node; icon?: string; spark?: Node }
export declare function StatCard(props: StatCardProps): React.ReactElement;
export interface WalletCardProps { available: number; held: number; transactions?: { title: Node; date: string; amount: number }[]; onTopUp?: () => void; onWithdraw?: () => void }
export declare function WalletCard(props: WalletCardProps): React.ReactElement;
export interface CampaignCardProps { name: string; sector: string; purpose: string; contract?: Contract; grade?: Grade; amount: number; raised: number; tenure: number; rate: string; investors: number; daysLeft: number; status?: Status; href?: string; state?: 'loading' }
export declare function CampaignCard(props: CampaignCardProps): React.ReactElement;
export interface DataTableColumn<R> { key: string; label: Node; align?: 'right'; mono?: boolean; sortable?: boolean; sortValue?: (row: R) => string | number; render?: (row: R) => Node }
export interface DataTableProps<R extends { id: string | number }> { columns: DataTableColumn<R>[]; rows: R[]; selectable?: boolean; pageSize?: number; density?: 'comfortable' | 'compact'; caption?: string; empty?: Node; loading?: boolean; forceStack?: boolean }
export declare function DataTable<R extends { id: string | number }>(props: DataTableProps<R>): React.ReactElement;
export interface DescriptionListProps { items: [Node, Node, boolean?][]; columns?: number }
export declare function DescriptionList(props: DescriptionListProps): React.ReactElement;
export interface RepaymentTimelineProps { items: { state: 'paid' | 'due' | 'late'; due: string; paidOn?: string; lateBy?: string; amount: number; of?: number }[] }
export declare function RepaymentTimeline(props: RepaymentTimelineProps): React.ReactElement;
/** variant 'nav' (PB-53): the wizard's progress bar; `current` is the step on screen and a step with `href` is a link. */
export interface OnboardingStepperProps { steps: { title: Node; description?: Node; done?: boolean; href?: string | null; actionLabel?: Node }[]; current?: number; orientation?: 'horizontal' | 'vertical'; variant?: 'checklist' | 'nav' }
export declare function OnboardingStepper(props: OnboardingStepperProps): React.ReactElement;
export interface DocumentRowProps { title?: Node; name: string; type?: 'pdf' | 'jpg' | 'png'; size: string; uploaded: string; status?: Status; href?: string; onView?: () => void; reason?: Node; action?: Node }
export declare function DocumentRow(props: DocumentRowProps): React.ReactElement;
/** PB-53: one fixed onboarding document slot; empty shows an upload box, filled shows the file with View and Replace. */
export interface DocumentSlotProps { slot: { type: string; label: string; description: string; document: { id: number; original_name: string; size_bytes: number; status: Status | 'approved'; rejection_reason?: string | null; uploaded_at: string; url: string } | null }; uploadUrl: string; maxBytes?: number; tooLargeMessage?: string; locked?: boolean }
export declare function DocumentSlot(props: DocumentSlotProps): React.ReactElement;
/** PB-53: the frame of every onboarding wizard step: steps, content, Back and Continue (or `actions`). */
export interface OnboardingFrameProps { wizard: { steps: { key: string; title: string; done: boolean; href: string | null }[]; step: string; backUrl: string | null; nextUrl: string | null; locked: boolean; role: 'borrower' | 'investor' }; onSubmit?: (e: React.FormEvent) => void; processing?: boolean; continueLabel?: Node; actions?: Node; children?: Node }
export declare function OnboardingFrame(props: OnboardingFrameProps): React.ReactElement;
export interface AvatarProps { name: string; size?: number }
export declare function Avatar(props: AvatarProps): React.ReactElement;

/* Navigation & overlays */
export interface TooltipProps { content: Node; children: React.ReactElement; side?: 'top' | 'bottom'; defaultOpen?: boolean }
export declare function Tooltip(props: TooltipProps): React.ReactElement;
export declare function Term(props: { definition: Node; children: Node; defaultOpen?: boolean }): React.ReactElement;
export interface PopoverProps { trigger: React.ReactElement; children: Node; defaultOpen?: boolean; align?: 'start' | 'end'; width?: number }
export declare function Popover(props: PopoverProps): React.ReactElement;
export type MenuItem = '-' | { heading: Node } | { icon?: string; label: Node; onSelect?: () => void; shortcut?: string; danger?: boolean; disabled?: boolean };
export interface DropdownMenuProps { trigger: React.ReactElement; items: MenuItem[]; defaultOpen?: boolean; align?: 'start' | 'end' }
export declare function DropdownMenu(props: DropdownMenuProps): React.ReactElement;
export interface TabsProps { tabs: { value: string; label: Node; count?: number }[]; defaultValue?: string; onChange?: (v: string) => void; children?: Node | ((active: string) => Node) }
export declare function Tabs(props: TabsProps): React.ReactElement;
export interface BreadcrumbsProps { items: { label: Node; href?: string }[] }
export declare function Breadcrumbs(props: BreadcrumbsProps): React.ReactElement;
export interface AccordionProps { items: { q: Node; a: Node }[]; defaultOpen?: number[] }
export declare function Accordion(props: AccordionProps): React.ReactElement;
export interface NavGroup { label?: string; items: { icon: string; label: string; active?: boolean; count?: number }[] }
export interface SidebarProps { portal?: 'Investor' | 'Borrower' | 'Admin'; groups?: NavGroup[]; collapsed?: boolean; footer?: Node; onNavigate?: (item: NavGroup['items'][number]) => void; collapsible?: boolean }
export interface ModeSwitchProps { mode?: Mode; onChange?: (m: Mode) => void; label?: string }
export declare function ModeSwitch(props: ModeSwitchProps): React.ReactElement;
export declare function Sidebar(props: SidebarProps): React.ReactElement;
export interface TopBarProps { title: Node; breadcrumb?: Node; shariahOn?: boolean; mode?: Mode; onModeChange?: (m: Mode) => void; unread?: number; user?: { name: string; role: string }; search?: boolean; onMenu?: () => void }
export declare function TopBar(props: TopBarProps): React.ReactElement;
export interface MobileNavProps { open: boolean; onClose: () => void; portal?: SidebarProps['portal']; groups?: NavGroup[]; footer?: Node }
export declare function MobileNav(props: MobileNavProps): React.ReactElement | null;

/* Signature */
export interface RiskExplanationProps { factors?: { label: string; effect: number }[]; summary?: Node; model?: string; state?: 'ready' | 'scoring' | 'pending' | 'failed'; onRetry?: () => void; howHref?: string }
export declare function RiskExplanation(props: RiskExplanationProps): React.ReactElement;

/* Charts */
export interface ChartFrameProps { title: Node; takeaway: Node; legend?: { label: string; color: string; dashed?: boolean; line?: boolean }[]; table?: { columns: Node[]; rows: Node[][] }; actions?: Node; children: Node }
export declare function ChartFrame(props: ChartFrameProps): React.ReactElement;
export interface FundingRingProps { value: number; max: number; size?: number; stroke?: number; label?: string }
export declare function FundingRing(props: FundingRingProps): React.ReactElement;
export interface DonutChartProps { data: { label: string; value: number; color?: string }[]; size?: number; thickness?: number; centerLabel?: string; format?: (v: number) => string }
export declare function DonutChart(props: DonutChartProps): React.ReactElement;
export interface ReturnsChartProps { data: { month: string; expected: number; received?: number }[]; height?: number }
export declare function ReturnsChart(props: ReturnsChartProps): React.ReactElement;
export interface PayoutsChartProps { data: { month: string; amount: number; status: 'received' | 'expected' }[]; height?: number }
export declare function PayoutsChart(props: PayoutsChartProps): React.ReactElement;
export interface RepaymentScheduleChartProps { data: { label: string; capital: number; ret: number }[]; height?: number }
export declare function RepaymentScheduleChart(props: RepaymentScheduleChartProps): React.ReactElement;
export interface HorizontalBarChartProps { data: { label: string; value: number; color?: string }[]; format?: (v: number) => string }
export declare function HorizontalBarChart(props: HorizontalBarChartProps): React.ReactElement;
export interface LineChartProps { data: { label: string; value: number }[]; height?: number; fmtY?: (v: number) => string; threshold?: number; label?: string }
export declare function LineChart(props: LineChartProps): React.ReactElement;
export interface SparklineProps { data: number[]; width?: number; height?: number; label?: string }
export declare function Sparkline(props: SparklineProps): React.ReactElement;
