<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Auth extends CI_Controller
{
    public function __construct()

    {
        parent::__construct();
        $this->load->library('form_validation');
    }
    public function index()
    {
        if ($this->session->userdata('email')) {
            redirect('user');
        }
        $this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email');
        $this->form_validation->set_rules('password', 'Password', 'trim|required');
        if ($this->form_validation->run() == false) {

            $data['title'] = 'Halaman Login';
            $this->load->view('templates/auth_header', $data);
            $this->load->view('auth/login');
            $this->load->view('templates/auth_footer');
        } else {
            $this->_login();
        }
    }

    private function _login()
    {
        $email = $this->input->post('email');
        $password = $this->input->post('password');

        $user = $this->db->get_where('user', ['email' => $email])->row_array();

        // usernya ada
        if ($user) {
            //jika user aktif
            if ($user['is_active'] == 1) {
                // cek password 
                if (password_verify($password, $user['password'])) {
                    $data = [
                        'email' => $user['email'],
                        'role_id' => $user['role_id']
                    ];
                    $this->session->set_userdata($data);
                    if ($user['role_id'] == 1) {
                        redirect('admin');
                    }
                    redirect('user');
                } else {
                    $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">
                Kata sandi salah!
              </div>');
                    redirect('auth');
                }
            } else {
                $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">
                Email tidak diaktifkan!
              </div>');
                redirect('auth');
            }
        } else {
            $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">
            Email tidak terdaftar!
          </div>');
            redirect('auth');
        }
    }

    public function registration()
    {
        if ($this->session->userdata('email')) {
            redirect('user');
        }
        $this->form_validation->set_rules('name', 'Nama', 'required|trim');
        $this->form_validation->set_rules('email', 'Email', 'required|trim|valid_email|is_unique[user.email]', [
            'is_unique' => 'Email ini telah terdaftar, silahkan gunakan email lain.'
        ]);
        $this->form_validation->set_rules('password1', 'Kata sandi', 'required|trim|min_length[5]|matches[password2]', [
            'matches' => 'Kata sandi tidak cocok',
            'min_length' => 'Kata sandi terlalu pendek'
        ]);
        $this->form_validation->set_rules('password2', 'Kata sandi', 'required|trim|matches[password1]');

        if ($this->form_validation->run() ==   false) {
            $data['title'] = 'Pendaftaran Pengguna';
            $this->load->view('templates/auth_header', $data);
            $this->load->view('auth/registration');
            $this->load->view('templates/auth_footer');
        } else {
            $email = $this->input->post('email', 'true');
            $data = [
                'name' => htmlspecialchars($this->input->post('name', 'true')),
                'email' => htmlspecialchars($email),
                'image' => 'default.jpg',
                'password' => password_hash($this->input->post('password1'), PASSWORD_DEFAULT),
                'role_id' => 2,
                'is_active' => 0,
                'date_created' => time()
            ];

            //siapkan token

            $token = bin2hex(openssl_random_pseudo_bytes(32));
            $user_token = [
                'email' => $email,
                'token' => $token,
                'date_created' => time()
            ];

            $this->db->insert('user', $data);
            $this->db->insert('user_token', $user_token);

            $this->_sendEmail($token, 'verify');

            $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">
            Akun telah dibuat. Cek email anda untuk aktivasi akun!
          </div>');
            redirect('auth');
        }
    }

    private function _sendEmail($token, $type)
    {
        $config = [
            'protocol' => 'smtp',
            'smtp_host' => 'ssl://smtp.googlemail.com',
            'smtp_user' => 'n.fauzan1997@gmail.com',
            'smtp_pass' => 'nurfauzan1997',
            'smtp_port' => 465,
            'mailtype' => 'html',
            'charset' => 'utf-8',
            'newline' => "\r\n"
        ];

        $this->load->library('email', $config);
        $this->email->initialize($config);

        $this->email->from('n.fauzan1997@gmail.com', 'Nur Fauzan');
        $this->email->to($this->input->post('email'));

        if ($type == 'verify') {

            $this->email->subject('Verifikasi Akun');
            $this->email->message('Klik link ini untuk aktivasi akun anda : <a href="' . base_url() . 'auth/verify?email=' . $this->input->post('email') . '&token=' . urlencode($token) . '">Aktivasi</a>');
        } else if ($type == 'forgot') {

            $this->email->subject('Reset Password');
            $this->email->message('Klik link ini untuk atur ulang kata sandi : <a href="' . base_url() . 'auth/resetpassword?email=' . $this->input->post('email') . '&token=' . urlencode($token) . '">Atur ulang</a>');
        }


        if ($this->email->send()) {
            return true;
        } else {
            echo $this->email->print_debugger();
            die;
        }
    }

    public function verify()
    {
        $email = $this->input->get('email');
        $token = $this->input->get('token');

        $user = $this->db->get_where('user', ['email' => $email])->row_array();

        if ($user) {
            $user_token = $this->db->get_where('user_token', ['token' => $token])->row_array();
            if ($user_token) {
                if (time() - $user_token['date_created'] < (60 * 60 * 24)) {

                    $this->db->set('is_active', 1);
                    $this->db->where('email', $email);
                    $this->db->update('user');

                    $this->db->delete('user_token', ['email' => $email]);

                    $this->session->set_flashdata('message', '<div class=" alert alert-success" role= "alert">' . $email . '
                berhasil di aktivasi. Silahkan login!
              </div>');
                    redirect('auth');
                } else {

                    $this->db->delete('user', ['email' => $email]);
                    $this->db->delete('user_token', ['email' => $email]);


                    $this->session->set_flashdata('message', '<div class=" alert alert-danger" role= "alert">
                Aktivasi akun gagal! Kode telah kadaluarsa.
              </div>');
                    redirect('admin/auth');
                    $this->session->set_flashdata('message', '<div class=" alert alert-danger" role= "alert">
                Aktivasi akun gagal! Kesalahan pada.
              </div>');
                    redirect('auth');
                }
            } else {
                $this->session->set_flashdata('message', '<div class=" alert alert-danger" role= "alert">
                Aktivasi akun gagal! Kesalahan pada.
              </div>');
                redirect('auth');
            }
        } else {
            $this->session->set_flashdata('message', '<div class=" alert alert-danger" role= "alert">
            Aktivasi akun gagal! Email salah.
          </div>');
            redirect('auth');
        }
    }

    public function logout()
    {
        $this->session->unset_userdata('email');
        $this->session->unset_userdata('role_id');

        $this->session->set_flashdata('message', '<div class=" alert alert-success" role= "alert">
            Anda berhasil logout!
          </div>');
        redirect('auth');
    }

    public function blocked()
    {
        $data['title'] = 'Akses Terlarang';
        $this->load->view('auth/blocked', $data);
    }

    public function forgotPassword()
    {
        $this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email');

        if ($this->form_validation->run() == false) {
            $data['title'] = 'Lupa Password';
            $this->load->view('templates/auth_header', $data);
            $this->load->view('auth/forgot-password');
            $this->load->view('templates/auth_footer');
        } else {
            $email = $this->input->post('email');
            $user = $this->db->get_where('user', ['email' => $email, 'is_active' => 1])->row_array();

            if ($user) {
                $token = bin2hex(openssl_random_pseudo_bytes(32));
                $user_token = [
                    'email' => $email,
                    'token' => $token,
                    'date_created' => time()
                ];

                $this->db->insert('user_token', $user_token);
                $this->_sendEmail($token, 'forgot');

                $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">Silahkan cek email anda untuk atur ulang kata sandi!</div>');
                redirect('auth/forgotpassword');
            } else {
                $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">Email tidak terdaftar atau dibekukan!</div>');
                redirect('auth/forgotpassword');
            }
        }
    }

    public function resetPassword()
    {
        $email = $this->input->get('email');
        $token = $this->input->get('token');

        $user = $this->db->get_where('user', ['email' => $email])->row_array();

        if ($user) {
            $user_token = $this->db->get_where('user_token', ['token' => $token])->row_array();

            if ($user_token) {
                $this->session->set_userdata('reset_email', $email);
                $this->changePassword();
            } else {
                $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">Atur ulang kata sandi gagal! Kesalahan pada token.</div>');
                redirect('auth');
            }
        } else {
            $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">Atur ulang kata sandi gagal! Kesalahan pada email.</div>');
            redirect('auth');
        }
    }

    public function changePassword()
    {

        if (!$this->session->userdata('reset_email')) {
            redirect('auth');
        }
        $this->form_validation->set_rules('password1', 'Password', 'trim|required|min_length[5]|matches[password2]');
        $this->form_validation->set_rules('password2', 'Password', 'trim|required|min_length[5]|matches[password1]');

        if ($this->form_validation->run() == false) {
            $data['title'] = 'Forgot Password';
            $this->load->view('templates/auth_header', $data);
            $this->load->view('auth/change-password');
            $this->load->view('templates/auth_footer');
        } else {
            $password = password_hash($this->input->post('password1'), PASSWORD_DEFAULT);

            $email = $this->session->userdata('reset_email');

            $this->db->set('password', $password);
            $this->db->where('email', $email);
            $this->db->update('user');

            $this->session->unset_userdata('reset_email');

            $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">Kata sandi berhasil diatur ulang. Silahkan Login!</div>');
            redirect('auth');
        }
    }
}
