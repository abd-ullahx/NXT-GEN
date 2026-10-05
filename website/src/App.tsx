import React, { useState } from 'react';
import { 
  Truck, 
  ShieldCheck, 
  Clock, 
  Video, 
  Building2, 
  Home, 
  ArrowRight, 
  CheckCircle2, 
  Sparkles, 
  Calendar, 
  UserCheck, 
  Send, 
  X,
  ExternalLink,
  MapPin,
  Phone,
  Mail
} from 'lucide-react';

export default function App() {
  const [showQuoteModal, setShowQuoteModal] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    from_location: '',
    to_location: '',
    move_type: 'Residential Move',
    notes: '',
  });
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);

  const handleSubmitQuote = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      const res = await fetch('/api/leads', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(formData),
      });

      if (res.ok) {
        setSubmitted(true);
      } else {
        alert('Failed to submit quote request. Please try again.');
      }
    } catch (err) {
      console.error(err);
      alert('Error submitting request. Please check your connection.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen flex flex-col font-sans bg-[#0a0c10] text-gray-100">
      {/* Navigation Header */}
      <header className="sticky top-0 z-40 bg-[#0c0f17]/80 backdrop-blur-md border-b border-white/10 px-6 py-4">
        <div className="max-w-7xl mx-auto flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-[#d4af37] to-[#aa820a] flex items-center justify-center text-black font-extrabold shadow-lg shadow-[#d4af37]/20">
              <Truck className="w-6 h-6" />
            </div>
            <div>
              <span className="text-xl font-bold tracking-tight text-white">NEXT GEN</span>
              <span className="text-xs uppercase tracking-widest text-[#d4af37] block font-semibold">Relocation Services</span>
            </div>
          </div>

          <nav className="hidden md:flex items-center space-x-8 text-sm font-medium">
            <a href="#services" className="text-gray-300 hover:text-[#d4af37] transition">Services</a>
            <a href="#portals" className="text-gray-300 hover:text-[#d4af37] transition">Portals</a>
            <a href="#survey" className="text-gray-300 hover:text-[#d4af37] transition">Virtual Survey</a>
            <a href="#contact" className="text-gray-300 hover:text-[#d4af37] transition">Contact</a>
          </nav>

          <div className="flex items-center space-x-4">
            <a
              href="/crm"
              className="px-4 py-2 text-sm font-semibold text-[#d4af37] border border-[#d4af37]/40 hover:border-[#d4af37] rounded-xl transition flex items-center space-x-2"
            >
              <span>CRM Panel</span>
              <ExternalLink className="w-4 h-4" />
            </a>
            <a
              href="/admin"
              className="px-4 py-2 text-sm font-semibold text-white bg-white/10 hover:bg-white/20 border border-white/10 rounded-xl transition flex items-center space-x-2"
            >
              <span>Admin Panel</span>
            </a>
            <button
              onClick={() => { setSubmitted(false); setShowQuoteModal(true); }}
              className="gold-gradient-btn px-5 py-2.5 rounded-xl text-sm font-bold flex items-center space-x-2"
            >
              <span>Get Free Quote</span>
            </button>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="flex-1">
        {/* Hero Section */}
        <section className="relative py-24 px-6 overflow-hidden">
          <div className="absolute top-10 left-1/2 -translate-x-1/2 w-[600px] h-[300px] bg-[#d4af37]/10 blur-[140px] pointer-events-none rounded-full" />
          
          <div className="max-w-7xl mx-auto text-center relative z-10">
            <div className="inline-flex items-center space-x-2 bg-[#d4af37]/10 border border-[#d4af37]/30 px-4 py-2 rounded-full text-[#d4af37] text-sm font-semibold mb-8">
              <Sparkles className="w-4 h-4" />
              <span>Next-Generation Moving & Storage Platform</span>
            </div>

            <h1 className="text-5xl md:text-7xl font-extrabold tracking-tight text-white max-w-4xl mx-auto leading-tight mb-6">
              Seamless Premium Relocation <br />
              <span className="gradient-text">Powered by Technology</span>
            </h1>

            <p className="text-lg md:text-xl text-gray-400 max-w-2xl mx-auto mb-10 leading-relaxed">
              Experience stress-free residential & commercial moves with instant online quotes, live video surveys, real-time tracking, and automated customer care.
            </p>

            <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
              <button
                onClick={() => { setSubmitted(false); setShowQuoteModal(true); }}
                className="gold-gradient-btn px-8 py-4 rounded-xl text-base font-bold flex items-center space-x-3 w-full sm:w-auto justify-center"
              >
                <span>Calculate Your Move Cost</span>
                <ArrowRight className="w-5 h-5" />
              </button>

              <a
                href="/crm"
                className="px-8 py-4 rounded-xl text-base font-bold bg-white/5 hover:bg-white/10 border border-white/15 text-white transition flex items-center space-x-3 w-full sm:w-auto justify-center"
              >
                <span>Open CRM Dashboard (/crm)</span>
                <ExternalLink className="w-5 h-5 text-[#d4af37]" />
              </a>
            </div>
          </div>
        </section>

        {/* Portal Access Cards */}
        <section id="portals" className="py-16 px-6 bg-[#0e111a] border-y border-white/5">
          <div className="max-w-7xl mx-auto">
            <div className="text-center mb-12">
              <h2 className="text-3xl font-bold text-white mb-3">System Access Portals</h2>
              <p className="text-gray-400">Direct navigation to platform operational environments</p>
            </div>

            <div className="grid md:grid-cols-2 gap-8">
              {/* CRM Panel Card */}
              <div className="card-glass p-8 relative group overflow-hidden">
                <div className="w-12 h-12 rounded-2xl bg-[#d4af37]/20 border border-[#d4af37]/40 flex items-center justify-center text-[#d4af37] mb-6">
                  <UserCheck className="w-6 h-6" />
                </div>
                <span className="text-xs font-mono uppercase tracking-widest text-[#d4af37]">Subpath /crm</span>
                <h3 className="text-2xl font-bold text-white mt-1 mb-3">React JS CRM Panel</h3>
                <p className="text-gray-400 mb-6 text-sm leading-relaxed">
                  Full-featured relocation management workspace. Handle leads, live video surveys, calendar scheduling, quote approvals, team chat, and driver assignments.
                </p>
                <a
                  href="/crm"
                  className="gold-gradient-btn inline-flex items-center space-x-2 px-6 py-3 rounded-xl text-sm"
                >
                  <span>Launch CRM Panel</span>
                  <ArrowRight className="w-4 h-4" />
                </a>
              </div>

              {/* Admin Panel Card */}
              <div className="card-glass p-8 relative group overflow-hidden">
                <div className="w-12 h-12 rounded-2xl bg-blue-500/20 border border-blue-500/40 flex items-center justify-center text-blue-400 mb-6">
                  <Building2 className="w-6 h-6" />
                </div>
                <span className="text-xs font-mono uppercase tracking-widest text-blue-400">Subpath /admin</span>
                <h3 className="text-2xl font-bold text-white mt-1 mb-3">Laravel Website Admin</h3>
                <p className="text-gray-400 mb-6 text-sm leading-relaxed">
                  Backend operations dashboard. Manage contacts, financial reports, integration keys, system settings, and core MySQL database entities.
                </p>
                <a
                  href="/admin"
                  className="px-6 py-3 rounded-xl text-sm font-bold bg-white/10 hover:bg-white/20 border border-white/15 text-white transition inline-flex items-center space-x-2"
                >
                  <span>Access Admin Control</span>
                  <ExternalLink className="w-4 h-4" />
                </a>
              </div>
            </div>
          </div>
        </section>

        {/* Services Grid */}
        <section id="services" className="py-20 px-6">
          <div className="max-w-7xl mx-auto">
            <div className="text-center mb-16">
              <h2 className="text-3xl md:text-4xl font-bold text-white mb-4">Our Relocation Services</h2>
              <p className="text-gray-400 max-w-2xl mx-auto">Tailored relocation solutions designed for seamless residential transitions and corporate relocations.</p>
            </div>

            <div className="grid md:grid-cols-3 gap-8">
              <div className="card-glass p-8">
                <div className="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center mb-6">
                  <Home className="w-6 h-6" />
                </div>
                <h3 className="text-xl font-bold text-white mb-3">Residential Moving</h3>
                <p className="text-gray-400 text-sm leading-relaxed">Full white-glove packing, heavy furniture assembly, fragile glass wrapping, and swift transport for homes and apartments.</p>
              </div>

              <div className="card-glass p-8">
                <div className="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center mb-6">
                  <Building2 className="w-6 h-6" />
                </div>
                <h3 className="text-xl font-bold text-white mb-3">Office & Commercial</h3>
                <p className="text-gray-400 text-sm leading-relaxed">Minimal operational downtime. Scheduled weekend office moves, IT equipment handling, and corporate office setup.</p>
              </div>

              <div className="card-glass p-8">
                <div className="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center mb-6">
                  <Video className="w-6 h-6" />
                </div>
                <h3 className="text-xl font-bold text-white mb-3">AI & Live Video Survey</h3>
                <p className="text-gray-400 text-sm leading-relaxed">No waiting for home visits. Perform a quick 10-minute live video walkthrough using your smartphone for instant inventory quotes.</p>
              </div>
            </div>
          </div>
        </section>
      </main>

      {/* Quote Request Modal */}
      {showQuoteModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-md">
          <div className="bg-[#141722] border border-[#d4af37]/30 rounded-2xl p-8 max-w-lg w-full relative shadow-2xl">
            <button
              onClick={() => setShowQuoteModal(false)}
              className="absolute top-4 right-4 text-gray-400 hover:text-white transition"
            >
              <X className="w-6 h-6" />
            </button>

            {submitted ? (
              <div className="text-center py-8">
                <div className="w-16 h-16 rounded-full bg-emerald-500/20 border border-emerald-500 text-emerald-400 flex items-center justify-center mx-auto mb-4">
                  <CheckCircle2 className="w-10 h-10" />
                </div>
                <h3 className="text-2xl font-bold text-white mb-2">Quote Request Received!</h3>
                <p className="text-gray-300 text-sm mb-6">
                  Thank you, <strong>{formData.name}</strong>. Our relocation specialists have logged your request into the CRM system.
                </p>
                <button
                  onClick={() => setShowQuoteModal(false)}
                  className="gold-gradient-btn px-6 py-2.5 rounded-xl text-sm font-bold"
                >
                  Done
                </button>
              </div>
            ) : (
              <>
                <h3 className="text-2xl font-bold text-white mb-2 flex items-center space-x-2">
                  <span>Get Instant Relocation Quote</span>
                </h3>
                <p className="text-xs text-gray-400 mb-6">Enter your details to register a lead directly into our backend system.</p>

                <form onSubmit={handleSubmitQuote} className="space-y-4">
                  <div>
                    <label className="block text-xs font-semibold text-gray-400 uppercase mb-1">Full Name</label>
                    <input
                      type="text"
                      required
                      value={formData.name}
                      onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                      placeholder="e.g. Sarah Jenkins"
                      className="w-full bg-[#1c202d] border border-gray-700 focus:border-[#d4af37] rounded-xl px-4 py-2.5 text-white text-sm outline-none transition"
                    />
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-semibold text-gray-400 uppercase mb-1">Email</label>
                      <input
                        type="email"
                        required
                        value={formData.email}
                        onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                        placeholder="sarah@example.com"
                        className="w-full bg-[#1c202d] border border-gray-700 focus:border-[#d4af37] rounded-xl px-4 py-2.5 text-white text-sm outline-none transition"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-gray-400 uppercase mb-1">Phone</label>
                      <input
                        type="text"
                        required
                        value={formData.phone}
                        onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                        placeholder="+44 7911 123456"
                        className="w-full bg-[#1c202d] border border-gray-700 focus:border-[#d4af37] rounded-xl px-4 py-2.5 text-white text-sm outline-none transition"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-semibold text-gray-400 uppercase mb-1">From Location</label>
                      <input
                        type="text"
                        required
                        value={formData.from_location}
                        onChange={(e) => setFormData({ ...formData, from_location: e.target.value })}
                        placeholder="e.g. London W1"
                        className="w-full bg-[#1c202d] border border-gray-700 focus:border-[#d4af37] rounded-xl px-4 py-2.5 text-white text-sm outline-none transition"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-gray-400 uppercase mb-1">To Location</label>
                      <input
                        type="text"
                        required
                        value={formData.to_location}
                        onChange={(e) => setFormData({ ...formData, to_location: e.target.value })}
                        placeholder="e.g. Manchester M1"
                        className="w-full bg-[#1c202d] border border-gray-700 focus:border-[#d4af37] rounded-xl px-4 py-2.5 text-white text-sm outline-none transition"
                      />
                    </div>
                  </div>

                  <button
                    type="submit"
                    disabled={submitting}
                    className="gold-gradient-btn w-full py-3 rounded-xl font-bold text-sm flex items-center justify-center space-x-2 mt-4"
                  >
                    <Send className="w-4 h-4" />
                    <span>{submitting ? 'Submitting Request...' : 'Submit Lead Quote Request'}</span>
                  </button>
                </form>
              </>
            )}
          </div>
        </div>
      )}

      {/* Footer */}
      <footer className="border-t border-white/10 bg-[#080a0e] py-12 px-6 text-sm text-gray-500">
        <div className="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6">
          <div className="flex items-center space-x-3">
            <div className="w-8 h-8 rounded-lg bg-[#d4af37] flex items-center justify-center text-black font-extrabold">
              <Truck className="w-5 h-5" />
            </div>
            <span className="text-white font-bold">Next Gen Relocation</span>
          </div>

          <div className="flex items-center space-x-6 text-xs font-medium">
            <a href="/crm" className="text-gray-400 hover:text-[#d4af37]">CRM Panel (/crm)</a>
            <a href="/admin" className="text-gray-400 hover:text-[#d4af37]">Website Admin (/admin)</a>
            <a href="/api/dashboard/stats" className="text-gray-400 hover:text-[#d4af37]">API Health Check</a>
          </div>

          <p className="text-xs">© {new Date().getFullYear()} Next Gen Relocation Ltd. All rights reserved.</p>
        </div>
      </footer>
    </div>
  );
}
