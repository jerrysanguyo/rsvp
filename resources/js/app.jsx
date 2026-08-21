import './bootstrap';
import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import Alert from './components/ui/Alert';
import { store as storeRequest } from './lib/http';

const Sparkle = ({ className = '' }) => <span className={`sparkle ${className}`} aria-hidden="true">✦</span>;

function formatEventDate(value) {
    if (!value) return 'Date to be announced';

    return new Intl.DateTimeFormat('en-PH', {
        month: 'long', day: 'numeric', year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}

const GuestIcon = () => (
    <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
    </svg>
);

function formatExpiration(value) {
    if (!value) return 'December 20, 2026';

    return new Intl.DateTimeFormat('en-PH', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function RsvpForm({ rsvpLink }) {
    const eventDetails = {
        date: formatEventDate(rsvpLink?.event_date),
        time: rsvpLink?.event_time ?? 'Time to be announced',
        venue: rsvpLink?.venue ?? 'Venue to be announced',
        mapUrl: rsvpLink?.venue_map_url,
    };
    const [attending, setAttending] = useState('yes');
    const [guests, setGuests] = useState(['']);
    const [submitted, setSubmitted] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [alert, setAlert] = useState(null);
    const [errors, setErrors] = useState({});
    const [receipt, setReceipt] = useState(null);
    const [submissionKey] = useState(() => window.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`);

    const addGuest = () => setGuests((current) => [...current, '']);
    const removeGuest = (index) => setGuests((current) => current.filter((_, i) => i !== index));
    const updateGuest = (index, value) => setGuests((current) => current.map((guest, i) => (i === index ? value : guest)));

    const chooseAttendance = (value) => {
        setAttending(value);
        if (value === 'no') setGuests((current) => [current[0] ?? '']);
    };

    const submitRsvp = async (event) => {
        event.preventDefault();
        setAlert(null);
        setErrors({});
        setSubmitting(true);

        try {
            const response = await storeRequest(rsvpLink.submission_url, {
                will_attend: attending === 'yes',
                participants: guests.map((fullName) => ({ full_name: fullName })),
            }, { idempotencyKey: submissionKey });

            setReceipt(response.data.data);
            setSubmitted(true);
        } catch (error) {
            setErrors(error.errors ?? {});
            setAlert({ variant: 'error', message: error.message ?? 'Your RSVP could not be submitted. Please try again.' });
        } finally {
            setSubmitting(false);
        }
    };

    if (submitted) {
        const guestCount = receipt?.participant_count ?? guests.filter((name) => name.trim()).length;
        return (
            <div className="success-card" role="status">
                <div className="success-crown">♛</div>
                <p className="eyebrow">Your reply was received</p>
                <h2>{attending === 'yes' ? 'See you in Fairytale Land!' : 'Thank you for letting us know'}</h2>
                <p>
                    {attending === 'yes'
                        ? `We saved ${guestCount} guest ${guestCount === 1 ? 'name' : 'names'} for Gaia’s celebration.`
                        : 'Gaia will miss you, but we truly appreciate your response.'}
                </p>
                <section className="receipt-guests" aria-labelledby="registered-guests-title">
                    <h3 id="registered-guests-title">{attending === 'yes' ? 'Registered guests' : 'Response registered for'}</h3>
                    <ul>
                        {(receipt?.participants ?? guests.map((fullName) => ({ full_name: fullName.trim() }))).map((participant, index) => (
                            <li key={`${participant.full_name}-${index}`}><span aria-hidden="true">✦</span>{participant.full_name}</li>
                        ))}
                    </ul>
                </section>
                {attending === 'yes' && (
                    <section className="receipt-event" aria-labelledby="event-details-title">
                        <h3 id="event-details-title">Event details</h3>
                        <dl>
                            <div><dt>Date</dt><dd>{eventDetails.date}</dd></div>
                            <div><dt>Time</dt><dd>{eventDetails.time}</dd></div>
                            <div><dt>Venue</dt><dd>{eventDetails.mapUrl ? <a href={eventDetails.mapUrl} target="_blank" rel="noreferrer">{eventDetails.venue} <span aria-hidden="true">↗</span></a> : eventDetails.venue}</dd></div>
                        </dl>
                    </section>
                )}
                {attending === 'no' && (
                    <section className="change-mind-note" aria-labelledby="change-mind-title">
                        <span className="change-mind-note__crown" aria-hidden="true">♛</span>
                        <div>
                            <h3 id="change-mind-title">The castle doors remain open</h3>
                            <p>If your plans change and you wish to join Gaia’s magical celebration, kindly message <strong>Marianne Abuan</strong> on <strong>Messenger</strong>.</p>
                        </div>
                    </section>
                )}
            </div>
        );
    }

    return (
        <form className="rsvp-form" onSubmit={submitRsvp} noValidate>
            <div className="form-heading">
                <div className="mini-crown" aria-hidden="true">♛</div>
                <p className="eyebrow">The royal invitation awaits</p>
                <h1>Will you join the magic?</h1>
                <p className="intro">Kindly send your response on or before {formatExpiration(rsvpLink?.expires_at)}.</p>
            </div>

            {alert && <Alert variant={alert.variant} message={alert.message} onDismiss={() => setAlert(null)} className="rsvp-form__alert" />}

            <fieldset className="attendance-fieldset">
                <legend>Will you be attending?</legend>
                <div className="attendance-options">
                    <button className={`attendance-card ${attending === 'yes' ? 'selected' : ''}`} type="button" aria-pressed={attending === 'yes'} onClick={() => chooseAttendance('yes')}>
                        <span className="choice-icon">♥</span>
                        <span><strong>Joyfully accepts</strong><small>We’ll be there!</small></span>
                        <span className="radio-mark" />
                    </button>
                    <button className={`attendance-card ${attending === 'no' ? 'selected' : ''}`} type="button" aria-pressed={attending === 'no'} onClick={() => chooseAttendance('no')}>
                        <span className="choice-icon">✦</span>
                        <span><strong>Sadly declines</strong><small>We can’t make it</small></span>
                        <span className="radio-mark" />
                    </button>
                </div>
            </fieldset>

            <div className="guest-section">
                <div className="section-label">
                    <span className="label-icon"><GuestIcon /></span>
                    <span>
                        <strong>{attending === 'yes' ? 'Who’s coming?' : 'Your full name'}</strong>
                        <small>{attending === 'yes' ? 'Please list each guest’s full name.' : 'Please tell us who is responding.'}</small>
                    </span>
                </div>

                <div className="guest-list">
                    {guests.map((guest, index) => (
                        <div className="guest-row" key={index}>
                            <label htmlFor={`guest-${index}`}>{index === 0 ? 'Full name' : `Guest ${index + 1}`}</label>
                            <div className="input-wrap">
                                <input id={`guest-${index}`} type="text" value={guest} maxLength={120} aria-invalid={Boolean(errors[`participants.${index}.full_name`])} aria-describedby={errors[`participants.${index}.full_name`] ? `guest-${index}-error` : undefined} placeholder={index === 0 ? 'e.g. Maria Santos' : 'Enter guest’s full name'} onChange={(event) => updateGuest(index, event.target.value)} required />
                                {index > 0 && <button type="button" className="remove-guest" onClick={() => removeGuest(index)} aria-label={`Remove guest ${index + 1}`}>×</button>}
                            </div>
                            {errors[`participants.${index}.full_name`] && <p className="guest-field-error" id={`guest-${index}-error`}>{errors[`participants.${index}.full_name`][0]}</p>}
                        </div>
                    ))}
                </div>
                {errors.participants && <p className="guest-field-error guest-field-error--group">{errors.participants[0]}</p>}

                {attending === 'yes' && guests.length < 8 && (
                    <button className="add-guest" type="button" onClick={addGuest}><span>＋</span> Add another guest</button>
                )}
            </div>

            <button className="submit-button" type="submit" disabled={submitting}>
                <span>{submitting ? 'Sending your RSVP…' : attending === 'yes' ? 'Send our royal RSVP' : 'Send my response'}</span>
                <span aria-hidden="true">→</span>
            </button>
            <p className="privacy-note">Your response will only be used for this celebration.</p>
        </form>
    );
}

function ClosedRsvp({ rsvpLink }) {
    const isExpired = rsvpLink?.status === 'expired' || (rsvpLink?.expires_at && new Date(rsvpLink.expires_at).getTime() <= Date.now());

    return (
        <div className="closed-rsvp" role="status">
            <div className="closed-rsvp__icon" aria-hidden="true">♛</div>
            <p className="eyebrow">{rsvpLink?.title ?? 'Royal invitation'}</p>
            <h1>This RSVP is closed</h1>
            <p>
                {isExpired
                    ? `The response period ended on ${formatExpiration(rsvpLink.expires_at)}.`
                    : 'This invitation is not accepting responses at the moment.'}
            </p>
            <span>Thank you for checking in.</span>
        </div>
    );
}

function InvitationModal({ onClose }) {
    useEffect(() => {
        const handleKeyDown = (event) => {
            if (event.key === 'Escape') onClose();
        };

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', handleKeyDown);

        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [onClose]);

    return (
        <div className="invitation-modal" role="dialog" aria-modal="true" aria-label="Full birthday invitation" onClick={onClose}>
            <div className="modal-toolbar">
                <span>Gaia’s royal invitation</span>
                <button type="button" onClick={onClose} aria-label="Close full invitation">×</button>
            </div>
            <div className="modal-image-wrap" onClick={(event) => event.stopPropagation()}>
                <img src="/images/rsvp_gaia.png" alt="Full invitation for Gaia’s third birthday at Jollibee Fairytale Land" />
            </div>
            <p className="zoom-hint">Pinch or double-tap to take a closer look</p>
        </div>
    );
}

function App({ rsvpLink }) {
    const [showInvitation, setShowInvitation] = useState(false);
    const isAvailable = !rsvpLink || (rsvpLink.is_available && new Date(rsvpLink.expires_at).getTime() > Date.now());

    return (
        <main className="page-shell">
            <div className="background-cloud cloud-one" />
            <div className="background-cloud cloud-two" />
            <Sparkle className="sparkle-one" />
            <Sparkle className="sparkle-two" />
            <Sparkle className="sparkle-three" />

            <section className="invitation-card">
                <div className="art-panel">
                    <img src="/images/rsvp_gaia.png" alt="Gaia’s third birthday invitation at Jollibee Fairytale Land" />
                    <button className="view-invitation-button" type="button" onClick={() => setShowInvitation(true)}>
                        <span aria-hidden="true">↗</span> View full invitation
                    </button>
                    <div className="mobile-event-details">
                        <div>
                            <span className="event-detail-icon" aria-hidden="true">◷</span>
                            <span><small>Date &amp; time</small><strong>{formatEventDate(rsvpLink?.event_date)} · {rsvpLink?.event_time ?? 'TBA'}</strong></span>
                        </div>
                        <div>
                            <span className="event-detail-icon" aria-hidden="true">⌖</span>
                            <span><small>Celebration venue</small><strong>{rsvpLink?.venue ?? 'Venue to be announced'}</strong></span>
                        </div>
                    </div>
                </div>
                <div className="form-panel">
                    <div className="floral-corner floral-top" aria-hidden="true">✿</div>
                    <div className="floral-corner floral-bottom" aria-hidden="true">❀</div>
                    {isAvailable ? <RsvpForm rsvpLink={rsvpLink} /> : <ClosedRsvp rsvpLink={rsvpLink} />}
                </div>
            </section>

            <footer>Made with love for Princess Gaia <span aria-hidden="true">♥</span></footer>
            {showInvitation && <InvitationModal onClose={() => setShowInvitation(false)} />}
        </main>
    );
}

const rootElement = document.getElementById('app');
const rsvpLink = JSON.parse(rootElement.dataset.rsvpLink || 'null');
createRoot(rootElement).render(<App rsvpLink={rsvpLink} />);
