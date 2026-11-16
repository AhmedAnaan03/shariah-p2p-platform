import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Card from '../../components/Card';
import DocumentList from '../../components/DocumentList';
import FileDropzone from '../../components/FileDropzone';
import Select from '../../components/Select';

/**
 * The signed-in borrower's or investor's KYC documents and the upload form (PB-09, PB-10).
 * One page for both roles: KycDocumentController sends the document types and upload URL for
 * the user's role.
 */
export default function DocumentsIndex({ documents, documentTypes, checklist, locked, uploadUrl, maxBytes, tooLargeMessage }) {
    const form = useForm({ document_type: '', file: null });
    // Changing the key gives a fresh, empty file input after a successful upload.
    const [fileInputKey, setFileInputKey] = useState(0);

    function submit(event) {
        event.preventDefault();
        // forceFormData: send as multipart/form-data, which a file upload needs.
        form.post(uploadUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setFileInputKey((key) => key + 1);
            },
        });
    }

    const missing = checklist.required_documents.filter((document) => !document.provided);

    return (
        <>
            {missing.length > 0 ? (
                <Alert tone="info" title="Still needed for verification">
                    {missing.map((document) => document.label).join(', ')}
                </Alert>
            ) : (
                <Alert tone="success">You have uploaded every document needed for verification.</Alert>
            )}

            <div className="grid gap-6 @bp-lg/shell:grid-cols-12">
                <Card title="Upload a document" className="@bp-lg/shell:col-span-5">
                    {locked ? (
                        <Alert tone="info" role="note">
                            Your account has been verified, so your documents can no longer be changed.
                        </Alert>
                    ) : (
                        <form onSubmit={submit} noValidate className="space-y-5">
                            <Select
                                id="document_type"
                                label="Document type"
                                placeholder="Choose a document type"
                                options={documentTypes}
                                value={form.data.document_type}
                                onChange={(value) => form.setData('document_type', value)}
                                error={form.errors.document_type}
                            />

                            <FileDropzone
                                key={fileInputKey}
                                id="file"
                                label="File"
                                hint="PDF, JPG or PNG. Files must be 5 MB or smaller."
                                accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                maxBytes={maxBytes}
                                tooLargeMessage={tooLargeMessage}
                                progress={form.progress?.percentage}
                                onChange={(file) => form.setData('file', file)}
                                error={form.errors.file}
                            />

                            <Button type="submit" iconLeft="upload-simple" loading={form.processing}>
                                Upload document
                            </Button>
                        </form>
                    )}
                </Card>

                <Card title="Your documents" className="@bp-lg/shell:col-span-7">
                    <DocumentList documents={documents} emptyMessage="Documents you upload appear here with their review status." />
                </Card>
            </div>
        </>
    );
}

DocumentsIndex.layout = {
    title: 'Documents',
    description: 'Documents are stored privately and encrypted. Only you and the platform’s administrators can open them.',
};
