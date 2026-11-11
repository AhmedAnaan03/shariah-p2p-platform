import PortalPlaceholder from '../../components/PortalPlaceholder';
import PortalLayout from '../../components/PortalLayout';

export default function InvestorPortal() {
    return (
        <PortalLayout title="Investor portal">
            <PortalPlaceholder
                heading="Investor portal"
                comingSoon={[
                    'Complete your profile and suitability acknowledgement',
                    'Browse campaigns and their risk scores',
                    'Pledge from your simulated wallet and track returns',
                ]}
            />
        </PortalLayout>
    );
}
