import React from 'react';

const icons = {
    success: (
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>
    ),
    error: (
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5M12 17h.01M10.3 3.9 2.2 18a2 2 0 0 0 1.7 3h16.2a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /></svg>
    ),
    info: (
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16v-4M12 8h.01M22 12A10 10 0 1 1 2 12a10 10 0 0 1 20 0Z" /></svg>
    ),
};

export default function Alert({ variant = 'info', title, message, onDismiss, className = '' }) {
    if (!message) return null;

    return (
        <div className={`ui-alert ui-alert--${variant} ${className}`} role={variant === 'error' ? 'alert' : 'status'} aria-live="polite">
            <span className="ui-alert__icon">{icons[variant] ?? icons.info}</span>
            <div className="ui-alert__content">
                {title && <strong>{title}</strong>}
                <p>{message}</p>
            </div>
            {onDismiss && (
                <button type="button" className="ui-alert__dismiss" onClick={onDismiss} aria-label="Dismiss message">×</button>
            )}
        </div>
    );
}
