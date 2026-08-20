import React from 'react';

export default function TextInput({ id, label, error, hint, icon, action, className = '', ...props }) {
    const descriptionId = error ? `${id}-error` : hint ? `${id}-hint` : undefined;

    return (
        <div className={`ui-field ${className}`}>
            <label htmlFor={id}>{label}</label>
            <div className={`ui-input-wrap ${error ? 'ui-input-wrap--error' : ''}`}>
                {icon && <span className="ui-input__icon">{icon}</span>}
                <input
                    id={id}
                    className={icon ? 'has-leading-icon' : ''}
                    aria-invalid={Boolean(error)}
                    aria-describedby={descriptionId}
                    {...props}
                />
                {action && <span className="ui-input__action">{action}</span>}
            </div>
            {error && <p id={`${id}-error`} className="ui-field__error">{error}</p>}
            {!error && hint && <p id={`${id}-hint`} className="ui-field__hint">{hint}</p>}
        </div>
    );
}
