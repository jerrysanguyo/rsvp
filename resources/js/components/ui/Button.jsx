import React from 'react';

export default function Button({ children, variant = 'primary', loading = false, fullWidth = false, className = '', disabled, ...props }) {
    return (
        <button
            className={`ui-button ui-button--${variant} ${fullWidth ? 'ui-button--full' : ''} ${className}`}
            disabled={disabled || loading}
            {...props}
        >
            {loading && <span className="ui-button__spinner" aria-hidden="true" />}
            <span>{loading ? 'Please wait…' : children}</span>
        </button>
    );
}
