import React from 'react';

export default function StatusBadge({ variant = 'neutral', children }) {
    return <span className={`ui-status ui-status--${variant}`}><span aria-hidden="true" />{children}</span>;
}
