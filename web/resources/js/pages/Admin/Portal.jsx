import PortalPlaceholder from '../../components/PortalPlaceholder';
import PortalLayout from '../../layouts/PortalLayout';

export default function AdminPortal() {
    return (
        <PortalLayout title="Admin portal">
            <PortalPlaceholder
                heading="Administrator portal"
                comingSoon={[
                    'Verify users and their KYC documents',
                    'Review and approve campaigns',
                    'Monitor the platform and audit log',
                ]}
            />
        </PortalLayout>
    );
}
