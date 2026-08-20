import './bootstrap';
import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';
import Alert from './components/ui/Alert';
import BrandMark from './components/ui/BrandMark';
import Button from './components/ui/Button';
import DataTable from './components/ui/DataTable';
import Modal from './components/ui/Modal';
import StatusBadge from './components/ui/StatusBadge';
import TextInput from './components/ui/TextInput';
import Toggle from './components/ui/Toggle';
import { destroy as destroyRequest, store as storeRequest, update as updateRequest } from './lib/http';

const EmailIcon = () => (
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm18 2-10 7L2 6" /></svg>
);

const LockIcon = () => (
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 11h14v10H5V11Zm3 0V7a4 4 0 0 1 8 0v4" /></svg>
);

const EyeIcon = ({ hidden }) => (
    <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
        <circle cx="12" cy="12" r="2.5" />
        {hidden && <path d="m4 4 16 16" />}
    </svg>
);

function LoginPage({ loginUrl, flash = {} }) {
    const [values, setValues] = useState({ email: '', password: '' });
    const [errors, setErrors] = useState({});
    const [showPassword, setShowPassword] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [alert, setAlert] = useState(
        flash.error ? { variant: 'error', message: flash.error } : flash.success ? { variant: 'success', message: flash.success } : null,
    );

    const updateValue = (event) => {
        const { name, value } = event.target;
        setValues((current) => ({ ...current, [name]: value }));
        setErrors((current) => ({ ...current, [name]: undefined }));
    };

    const validate = () => {
        const nextErrors = {};
        if (!values.email.trim()) nextErrors.email = 'Email address is required.';
        else if (!/^\S+@\S+\.\S+$/.test(values.email)) nextErrors.email = 'Enter a valid email address.';
        if (!values.password) nextErrors.password = 'Password is required.';
        setErrors(nextErrors);
        return Object.keys(nextErrors).length === 0;
    };

    const submit = async (event) => {
        event.preventDefault();
        setAlert(null);
        if (!validate()) return;

        setSubmitting(true);
        try {
            const response = await storeRequest(loginUrl, values);
            setAlert({ variant: 'success', message: response.data.message });
            window.setTimeout(() => window.location.assign(response.data.redirect), 450);
        } catch (error) {
            const status = error.status;
            const responseErrors = error.errors ?? {};
            setErrors(Object.fromEntries(Object.entries(responseErrors).map(([key, messages]) => [key, messages[0]])));

            let message = error.message ?? 'We could not sign you in. Please try again.';
            if (status === 422 && responseErrors.email) message = 'The email or password is incorrect, or the account is inactive.';
            if (status === 419) message = 'Your secure session expired. Refresh the page and try again.';
            setAlert({ variant: 'error', message });
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <main className="admin-auth-shell">
            <section className="admin-auth-card">
                <aside className="admin-auth-art">
                    <img src="/images/rsvp_gaia.png" alt="Gaia’s Fairytale Land birthday invitation" />
                    <div className="admin-auth-art__overlay">
                        <span className="admin-auth-art__badge">Private administration</span>
                        <h1>Every royal guest,<br />beautifully organized.</h1>
                        <p>Manage invitations and responses from one secure place.</p>
                    </div>
                </aside>

                <div className="admin-auth-form-panel">
                    <div className="admin-auth-form-wrap">
                        <BrandMark />
                        <div className="admin-auth-heading">
                            <p className="eyebrow">Welcome back</p>
                            <h2>Sign in to your dashboard</h2>
                            <p>Use your administrator email and password.</p>
                        </div>

                        {alert && <Alert variant={alert.variant} message={alert.message} onDismiss={() => setAlert(null)} />}

                        <form className="admin-login-form" onSubmit={submit} noValidate>
                            <TextInput
                                id="email"
                                name="email"
                                type="email"
                                label="Email address"
                                value={values.email}
                                error={errors.email}
                                icon={<EmailIcon />}
                                onChange={updateValue}
                                placeholder="admin@example.com"
                                autoComplete="username"
                                autoCapitalize="none"
                                spellCheck="false"
                                disabled={submitting}
                            />
                            <TextInput
                                id="password"
                                name="password"
                                type={showPassword ? 'text' : 'password'}
                                label="Password"
                                value={values.password}
                                error={errors.password}
                                icon={<LockIcon />}
                                onChange={updateValue}
                                placeholder="Enter your password"
                                autoComplete="current-password"
                                disabled={submitting}
                                action={(
                                    <button type="button" onClick={() => setShowPassword((current) => !current)} aria-label={showPassword ? 'Hide password' : 'Show password'}>
                                        <EyeIcon hidden={showPassword} />
                                    </button>
                                )}
                            />
                            <Button type="submit" fullWidth loading={submitting}>Sign in securely <span aria-hidden="true">→</span></Button>
                        </form>

                        <p className="admin-security-note"><span aria-hidden="true">◆</span> Protected by secure sessions and login attempt limits.</p>
                    </div>
                </div>
            </section>
        </main>
    );
}

function RsvpLinkFormModal({ open, storeUrl, onClose, onCreated }) {
    const [values, setValues] = useState({ title: '', expires_at: '', is_active: true });
    const [errors, setErrors] = useState({});
    const [alert, setAlert] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    const resetAndClose = () => {
        if (submitting) return;
        setValues({ title: '', expires_at: '', is_active: true });
        setErrors({});
        setAlert(null);
        onClose();
    };

    const updateValue = (event) => {
        const { name, value } = event.target;
        setValues((current) => ({ ...current, [name]: value }));
        setErrors((current) => ({ ...current, [name]: undefined }));
    };

    const validate = () => {
        const nextErrors = {};
        if (!values.title.trim()) nextErrors.title = 'Link name is required.';
        if (!values.expires_at) nextErrors.expires_at = 'Expiration date and time are required.';
        else if (new Date(values.expires_at).getTime() <= Date.now()) nextErrors.expires_at = 'Expiration must be in the future.';
        setErrors(nextErrors);
        return Object.keys(nextErrors).length === 0;
    };

    const submit = async (event) => {
        event.preventDefault();
        setAlert(null);
        if (!validate()) return;

        setSubmitting(true);
        try {
            const response = await storeRequest(storeUrl, {
                title: values.title.trim(),
                expires_at: new Date(values.expires_at).toISOString(),
                is_active: values.is_active,
            });
            onCreated(response.data.data, response.data.message);
            setValues({ title: '', expires_at: '', is_active: true });
            setErrors({});
            onClose();
        } catch (error) {
            const responseErrors = error.errors ?? {};
            setErrors(Object.fromEntries(Object.entries(responseErrors).map(([key, messages]) => [key, messages[0]])));
            setAlert({ variant: 'error', message: error.message });
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Modal open={open} title="Create an RSVP link" description="Generate a secure public link for the Fairytale Land RSVP form." onClose={resetAndClose}>
            <form className="rsvp-link-form" onSubmit={submit} noValidate>
                {alert && <Alert variant={alert.variant} message={alert.message} onDismiss={() => setAlert(null)} />}
                <TextInput
                    id="rsvp-link-title"
                    name="title"
                    type="text"
                    label="Link name"
                    value={values.title}
                    error={errors.title}
                    onChange={updateValue}
                    placeholder="e.g. Gaia’s 3rd Birthday"
                    maxLength={120}
                    disabled={submitting}
                />
                <TextInput
                    id="rsvp-link-expiration"
                    name="expires_at"
                    type="datetime-local"
                    label="Expiration date and time"
                    value={values.expires_at}
                    error={errors.expires_at}
                    hint="The public form closes automatically at this time."
                    onChange={updateValue}
                    disabled={submitting}
                />
                <Toggle
                    id="rsvp-link-active"
                    label="Activate link immediately"
                    description="Guests can open the public form as soon as it is created."
                    checked={values.is_active}
                    onChange={(checked) => setValues((current) => ({ ...current, is_active: checked }))}
                    disabled={submitting}
                />
                <div className="ui-modal__actions">
                    <Button type="button" variant="secondary" onClick={resetAndClose} disabled={submitting}>Cancel</Button>
                    <Button type="submit" loading={submitting}>Create secure link</Button>
                </div>
            </form>
        </Modal>
    );
}

function DeleteRsvpLinkModal({ link, submitting, onClose, onConfirm }) {
    return (
        <Modal open={Boolean(link)} title="Remove RSVP link?" description="This public URL will stop working immediately." onClose={() => !submitting && onClose()} size="small">
            <div className="delete-link-confirmation">
                <div className="delete-link-confirmation__icon" aria-hidden="true">!</div>
                <p><strong>{link?.title}</strong> will be removed. Existing participant data will not be affected when responses are connected later.</p>
            </div>
            <div className="ui-modal__actions">
                <Button type="button" variant="secondary" onClick={onClose} disabled={submitting}>Keep link</Button>
                <Button type="button" variant="danger" loading={submitting} onClick={onConfirm}>Remove link</Button>
            </div>
        </Modal>
    );
}

const formatAdminDate = (value) => new Intl.DateTimeFormat('en-PH', {
    month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit',
}).format(new Date(value));

function DashboardPage({ user, logoutUrl, rsvpLinks: initialRsvpLinks = [], rsvpLinkStoreUrl, participants = [] }) {
    const [submitting, setSubmitting] = useState(false);
    const [alert, setAlert] = useState(null);
    const [rsvpLinks, setRsvpLinks] = useState(initialRsvpLinks);
    const [createLinkOpen, setCreateLinkOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [linkBusy, setLinkBusy] = useState(null);

    const logout = async () => {
        setSubmitting(true);
        setAlert(null);
        try {
            const response = await destroyRequest(logoutUrl);
            window.location.assign(response.data.redirect);
        } catch (error) {
            setAlert({ variant: 'error', message: error.message ?? 'Unable to sign out. Please try again.' });
            setSubmitting(false);
        }
    };

    const linkCreated = (link, message) => {
        setRsvpLinks((current) => [link, ...current]);
        setAlert({ variant: 'success', message });
    };

    const copyPublicLink = async (link) => {
        try {
            await navigator.clipboard.writeText(link.public_url);
            setAlert({ variant: 'success', message: `Public link for “${link.title}” copied to your clipboard.` });
        } catch {
            setAlert({ variant: 'error', message: 'The link could not be copied automatically. Open it and copy the address from your browser.' });
        }
    };

    const toggleLink = async (link) => {
        setLinkBusy(link.id);
        setAlert(null);
        try {
            const response = await updateRequest(link.update_url, { is_active: !link.is_active }, { method: 'patch' });
            setRsvpLinks((current) => current.map((item) => (item.id === link.id ? response.data.data : item)));
            setAlert({ variant: 'success', message: response.data.message });
        } catch (error) {
            setAlert({ variant: 'error', message: error.message });
        } finally {
            setLinkBusy(null);
        }
    };

    const removeLink = async () => {
        if (!deleteTarget) return;
        setLinkBusy(deleteTarget.id);
        setAlert(null);
        try {
            const response = await destroyRequest(deleteTarget.destroy_url);
            setRsvpLinks((current) => current.filter((link) => link.id !== deleteTarget.id));
            setAlert({ variant: 'success', message: response.data.message });
            setDeleteTarget(null);
        } catch (error) {
            setAlert({ variant: 'error', message: error.message });
        } finally {
            setLinkBusy(null);
        }
    };

    const attendingCount = participants.filter((participant) => participant.attendance === 'attending').length;
    const declinedCount = participants.length - attendingCount;
    const attendanceRate = participants.length > 0 ? Math.round((attendingCount / participants.length) * 100) : 0;
    const activeLinkCount = rsvpLinks.filter((link) => link.status === 'active').length;
    const participantColumns = [
        {
            key: 'name',
            label: 'Participant',
            render: (participant) => (
                <div className="participant-cell">
                    <span>{participant.initials}</span>
                    <span><strong>{participant.name}</strong><small>RSVP participant</small></span>
                </div>
            ),
        },
        {
            key: 'attendance',
            label: 'Response',
            render: (participant) => (
                <StatusBadge variant={participant.attendance === 'attending' ? 'success' : 'declined'}>
                    {participant.attendance === 'attending' ? 'Will attend' : 'Will not attend'}
                </StatusBadge>
            ),
        },
        { key: 'invitation', label: 'RSVP link', render: (participant) => <span className="invitation-name">{participant.invitation}</span> },
        { key: 'submittedAt', label: 'Submitted', render: (participant) => <time dateTime={participant.submittedAt}>{participant.submittedLabel}</time> },
        {
            key: 'actions',
            label: '',
            sortable: false,
            className: 'data-table__actions',
            render: (participant) => (
                <button type="button" onClick={() => setAlert({ variant: 'info', message: `Detailed response information for ${participant.name} will be available once participant submissions are connected.` })}>View</button>
            ),
        },
    ];

    return (
        <main className="admin-dashboard-shell">
            <header className="admin-topbar">
                <BrandMark compact />
                <div className="admin-topbar__account">
                    <span className="admin-avatar">{user.name.slice(0, 1).toUpperCase()}</span>
                    <span><strong>{user.name}</strong><small>{user.email}</small></span>
                    <Button type="button" variant="secondary" loading={submitting} onClick={logout}>Sign out</Button>
                </div>
            </header>

            <section className="admin-dashboard-content">
                {alert && <Alert variant={alert.variant} message={alert.message} onDismiss={() => setAlert(null)} />}
                <div className="dashboard-welcome">
                    <div><p className="eyebrow">Royal celebration manager</p><h1>Welcome, {user.name}</h1><p>Your secure admin workspace is ready.</p></div>
                    <div className="dashboard-welcome__action">
                        <span className="dashboard-welcome__crown" aria-hidden="true">♛</span>
                        <Button type="button" onClick={() => setCreateLinkOpen(true)}>＋ Create RSVP link</Button>
                    </div>
                </div>

                <section className="rsvp-links-panel">
                    <div className="rsvp-links-panel__heading">
                        <div><p className="eyebrow">Public invitations</p><h2>RSVP links</h2><p>Create and control the links shared with your guests.</p></div>
                        <Button type="button" variant="secondary" onClick={() => setCreateLinkOpen(true)}>＋ New link</Button>
                    </div>

                    {rsvpLinks.length === 0 ? (
                        <div className="rsvp-links-empty">
                            <span aria-hidden="true">⌁</span>
                            <h3>No RSVP links yet</h3>
                            <p>Create your first secure public link for the Fairytale Land form.</p>
                            <Button type="button" onClick={() => setCreateLinkOpen(true)}>Create first link</Button>
                        </div>
                    ) : (
                        <div className="rsvp-link-grid">
                            {rsvpLinks.map((link) => (
                                <article className="rsvp-link-card" key={link.id}>
                                    <div className="rsvp-link-card__top">
                                        <span className="rsvp-link-card__icon" aria-hidden="true">⌁</span>
                                        <StatusBadge variant={link.status === 'active' ? 'success' : link.status === 'expired' ? 'neutral' : 'declined'}>
                                            {link.status === 'active' ? 'Active' : link.status === 'expired' ? 'Expired' : 'Inactive'}
                                        </StatusBadge>
                                    </div>
                                    <h3>{link.title}</h3>
                                    <button className="rsvp-link-card__url" type="button" onClick={() => copyPublicLink(link)} title="Copy public URL">{link.public_url}</button>
                                    <div className="rsvp-link-card__expiry"><span aria-hidden="true">◷</span><span><small>Expires</small><strong>{formatAdminDate(link.expires_at)}</strong></span></div>
                                    <div className="rsvp-link-card__actions">
                                        <a href={link.public_url} target="_blank" rel="noreferrer">Open form ↗</a>
                                        <button type="button" onClick={() => copyPublicLink(link)}>Copy</button>
                                        <button type="button" onClick={() => toggleLink(link)} disabled={linkBusy === link.id}>{linkBusy === link.id ? 'Saving…' : link.is_active ? 'Deactivate' : 'Activate'}</button>
                                        <button className="is-danger" type="button" onClick={() => setDeleteTarget(link)} disabled={linkBusy === link.id}>Remove</button>
                                    </div>
                                </article>
                            ))}
                        </div>
                    )}
                </section>

                <div className="dashboard-stat-grid">
                    <article><span>Total participants</span><strong>{participants.length}</strong><small>{participants.length > 0 ? 'Across all RSVP responses' : 'No responses yet'}</small></article>
                    <article><span>Will attend</span><strong>{attendingCount}</strong><small>{participants.length > 0 ? `${attendanceRate}% attendance rate` : 'No responses yet'}</small></article>
                    <article><span>Will not attend</span><strong>{declinedCount}</strong><small>{participants.length > 0 ? 'Recorded responses' : 'No responses yet'}</small></article>
                    <article><span>Active RSVP links</span><strong>{activeLinkCount}</strong><small>{rsvpLinks.length} total created</small></article>
                </div>

                <section className="participants-panel">
                    <div className="participants-panel__heading">
                        <div>
                            <div className="participants-panel__title-row"><h2>Participants</h2></div>
                            <p>Review everyone who responded to an RSVP invitation.</p>
                        </div>
                        <Button type="button" variant="secondary" onClick={() => setAlert({ variant: 'info', message: 'CSV export will be enabled when participant data is connected to the backend.' })}>Export CSV</Button>
                    </div>
                    <DataTable
                        rows={participants}
                        columns={participantColumns}
                        searchableKeys={['name', 'invitation']}
                        searchPlaceholder="Search participant name…"
                        filterKey="attendance"
                        filterOptions={[
                            { value: 'attending', label: 'Will attend' },
                            { value: 'declined', label: 'Will not attend' },
                        ]}
                        emptyMessage="No participant responses yet."
                    />
                </section>
            </section>
            <RsvpLinkFormModal open={createLinkOpen} storeUrl={rsvpLinkStoreUrl} onClose={() => setCreateLinkOpen(false)} onCreated={linkCreated} />
            <DeleteRsvpLinkModal link={deleteTarget} submitting={linkBusy === deleteTarget?.id} onClose={() => setDeleteTarget(null)} onConfirm={removeLink} />
        </main>
    );
}

const rootElement = document.getElementById('admin-app');
const page = rootElement.dataset.page;
const payload = JSON.parse(rootElement.dataset.payload || '{}');
createRoot(rootElement).render(page === 'dashboard' ? <DashboardPage {...payload} /> : <LoginPage {...payload} />);
