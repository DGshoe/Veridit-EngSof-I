import './PageLayout.css';

export function PageLayout({ tag, title, description, action, children }) {
  return (
    <main className="page-layout-main">
      <div className="page-layout-container">
        
        <header className="page-layout-header-wrapper">
          <div className="page-layout-header">
            {tag && <span className="page-layout-tag">{tag}</span>}
            <h2 className="page-layout-title">{title}</h2>
            {description && <p className="page-layout-description">{description}</p>}
          </div>
          
          {action && (
            <div className="page-layout-action">
              {action}
            </div>
          )}
        </header>
        
        <section className="page-layout-section">
          {children}
        </section>
      </div>
    </main>
  );
}
