import React from 'react';

export default function Toggle({ id, label, description, checked, onChange, disabled = false }) {
    return (
        <label className={`ui-toggle ${disabled ? 'is-disabled' : ''}`} htmlFor={id}>
            <span><strong>{label}</strong>{description && <small>{description}</small>}</span>
            <input id={id} type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} disabled={disabled} />
            <span className="ui-toggle__control" aria-hidden="true"><span /></span>
        </label>
    );
}
