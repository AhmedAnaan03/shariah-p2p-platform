import BulletList from './BulletList';
import Card from './Card';

/**
 * Body of a placeholder portal page: a heading and a list of what later backlog items add.
 */
export default function PortalPlaceholder({ heading, comingSoon }) {
    return (
        <Card title={heading} titleAs="h1" description="This portal is a placeholder. Coming in later sprints:">
            <BulletList items={comingSoon} />
        </Card>
    );
}
