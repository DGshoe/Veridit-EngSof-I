/**
 * Labeled input that shows the API validation errors (422) for its field.
 *   <FormField label="E-mail" name="email" type="email" value={...} onInput={...} errors={errors} />
 */
export function FormField({ label, name, errors = {}, hint, ...inputProps }) {
  const fieldErrors = errors[name] || [];
  const id = `field-${name}`;

  return (
    <div className={`input-group ${fieldErrors.length ? 'has-error' : ''}`}>
      <label htmlFor={id}>{label}</label>
      <input
        id={id}
        name={name}
        aria-invalid={fieldErrors.length > 0}
        aria-describedby={fieldErrors.length ? `${id}-error` : undefined}
        {...inputProps}
      />
      {hint && !fieldErrors.length && <small className="field-hint">{hint}</small>}
      {fieldErrors.length > 0 && (
        <small id={`${id}-error`} className="field-error">{fieldErrors.join(' ')}</small>
      )}
    </div>
  );
}

/** Message box for success/error feedback. */
export function Alert({ type = 'error', children }) {
  if (!children) return null;
  return <div className={`form-alert form-alert-${type}`} role={type === 'error' ? 'alert' : 'status'}>{children}</div>;
}
