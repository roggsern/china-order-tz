import { buildReplyPayload, mapSupportTicket } from './supportApi';

describe('supportApi', () => {
  it('maps support ticket with messages', () => {
    const ticket = mapSupportTicket({
      id: 't1',
      ticket_number: 'SUP-001',
      subject: 'Delivery issue',
      status: 'open',
      status_label: 'Open',
      messages: [{ id: 'm1', message: 'Hello', sender_type: 'customer' }],
    });

    expect(ticket.ticket_number).toBe('SUP-001');
    expect(ticket.messages?.[0]?.message).toBe('Hello');
  });

  it('builds reply payload', () => {
    expect(buildReplyPayload('  Thanks  ', true)).toEqual({
      message: 'Thanks',
      waiting_for_customer: true,
    });
  });
});
