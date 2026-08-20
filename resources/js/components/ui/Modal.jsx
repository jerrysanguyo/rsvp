import React, { useEffect, useRef } from 'react';

export default function Modal({ open, title, description, children, onClose, size = 'medium' }) {
    const closeButtonRef = useRef(null);
    const onCloseRef = useRef(onClose);
    onCloseRef.current = onClose;

    useEffect(() => {
        if (!open) return undefined;

        const previousOverflow = document.body.style.overflow;
        const handleKeyDown = (event) => {
            if (event.key === 'Escape') onCloseRef.current();
        };

        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', handleKeyDown);
        closeButtonRef.current?.focus();

        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [open]);

    if (!open) return null;

    return (
        <div className="ui-modal" role="presentation" onMouseDown={onClose}>
            <section className={`ui-modal__dialog ui-modal__dialog--${size}`} role="dialog" aria-modal="true" aria-labelledby="ui-modal-title" onMouseDown={(event) => event.stopPropagation()}>
                <header className="ui-modal__header">
                    <div><h2 id="ui-modal-title">{title}</h2>{description && <p>{description}</p>}</div>
                    <button ref={closeButtonRef} type="button" onClick={onClose} aria-label="Close dialog">×</button>
                </header>
                <div className="ui-modal__body">{children}</div>
            </section>
        </div>
    );
}
