import PortalPlaceholder from '../../components/PortalPlaceholder';
import PortalLayout from '../../layouts/PortalLayout';

export default function BorrowerPortal() {
    return (
        <PortalLayout title="Borrower portal">
            <PortalPlaceholder
                heading="Borrower / SME portal"
                comingSoon={[
                    'Complete your profile and upload KYC documents',
                    'Create a financing campaign',
                    'Track repayments',
                ]}
            />
        </PortalLayout>
    );
}
