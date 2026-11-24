# Plugin AddressVN — Địa chỉ Việt Nam

> 🌐 **Ngôn ngữ:** 🇻🇳 Tiếng Việt (hiện tại) · [🇬🇧 English](./readme.md) · [🇯🇵 日本語](./readme_ja.md)

Khách chọn **Tỉnh/Thành phố** rồi chọn **Phường/Xã** từ danh sách khi đăng ký, sửa sổ địa chỉ và đặt hàng trên cửa hàng
S-Cart — thay cho việc tự gõ. Địa chỉ được lưu bằng tên chính thức kèm mã đơn vị hành chính, nên không còn cảnh mỗi khách
viết một kiểu ("Q.1", "Quận 1", "quan 1").

Danh mục dùng địa giới **hai cấp** (tỉnh → xã, từ 01/7/2025) do Cục Thống kê công bố, đóng gói sẵn trong plugin.

---

## Tổng quan

| | |
| --- | --- |
| **Plugin** | AddressVN |
| **Phiên bản** | 1.0.0 |
| **Nhà phát triển** | GP247 |
| **Giấy phép** | Miễn phí · mã nguồn mở, [MIT](./LICENSE) |
| **Yêu cầu** | GP247 Core **3.18+** · package `gp247/shop` **3.15+** |
| **Không cần** | Composer package mới, cron, queue, kết nối Internet lúc chạy |
| **Danh mục** | 34 tỉnh/thành phố · 3.321 phường/xã/đặc khu — bản ngày **24/11/2026** |

## Tính năng

- Ô **Tỉnh/Thành phố** và **Phường/Xã** thành danh sách chọn; danh sách phường/xã chỉ gồm đơn vị của tỉnh đã chọn.
- Áp dụng ở mọi nơi nhập địa chỉ: đăng ký, thông tin tài khoản, sổ địa chỉ, bước đặt hàng; và ở màn quản trị: sửa đơn,
  tạo đơn, khách hàng, sổ địa chỉ của khách.
- Lưu **tên chính thức** vào đơn hàng / địa chỉ (hiển thị, in hoá đơn, xuất dữ liệu vẫn như cũ) kèm **mã tỉnh, mã xã** để
  plugin vận chuyển hay báo cáo dùng về sau.
- Địa chỉ cũ gõ tay vẫn mở được: khi sửa, plugin tự khớp tên đã gõ (không dấu, thiếu chữ "Phường", "Tỉnh"…) với danh mục.
  Hai đơn vị chỉ khác nhau ở dấu (vd "Xã Văn Lang" và "Xã Văn Lăng") không bao giờ bị khớp nhầm: gõ không dấu thì khách
  được yêu cầu chọn lại từ danh sách.
- Khách ở nước khác vẫn gõ địa chỉ như thường. Danh mục lỗi hoặc thiếu thì ô chọn tự trở về ô nhập tay — khách vẫn đặt
  được hàng.
- Bật/tắt theo từng cửa hàng (site nhiều cửa hàng).

## Cài đặt

1. Chép thư mục `AddressVN` vào `app/GP247/Plugins/` trên website (hoặc tải file ZIP lên ở
   **Admin → Extensions / Plugins → Import**).
2. Vào **Admin → Extensions / Plugins**, tìm **Địa chỉ Việt Nam** và bấm **Install**.

Plugin được bật ngay sau khi cài. Site chưa cập nhật lên `gp247/shop` 3.15 sẽ thấy thông báo cần cập nhật S-Cart trước
(`composer update` rồi `php artisan gp247:update`).

## Cấu hình

Bấm nút cài đặt của plugin (hoặc vào **Quản lý hệ thống → Cấu hình website → tab Địa chỉ**, chọn **Việt Nam**):

| Trường của cửa hàng | Lấy dữ liệu từ (mặc định) |
| --- | --- |
| Tỉnh/Thành phố (`city`) | Tỉnh/Thành phố |
| Phường/Xã (`ward`) | Phường/Xã |
| Các trường còn lại (số nhà, đường, quận/huyện cũ…) | Nhập tay |

Mặc định đã dùng được ngay, chỉ cần hai việc:

1. Ở **tab Khách hàng** của cùng màn, **bật** hai trường Tỉnh/Thành phố và Phường/Xã (và trường Quốc gia nếu bạn bán cho
   nhiều nước). Khung kiểm tra ở tab Địa chỉ báo ngay trường nào đang tắt, kèm nút mở đúng chỗ sửa.
2. Tuỳ chọn **Bắt buộc khớp danh mục** (bật sẵn): địa chỉ Việt Nam không có trong danh mục bị từ chối và khách được yêu
   cầu chọn lại. Tắt thì vẫn nhận địa chỉ đó, chỉ không có mã. Nên tắt nếu giao diện cửa hàng đang dùng chưa cập nhật khối
   địa chỉ mới (khung kiểm tra ở tab Địa chỉ sẽ báo).

Gỡ plugin không xoá cấu hình ở tab Địa chỉ — cài lại là dùng tiếp.

## Cập nhật danh mục

Địa giới hành chính thay đổi theo nghị quyết của Quốc hội. Mỗi bản plugin ghi rõ ngày của danh mục (bảng Tổng quan).
Khi có bản plugin mới với danh mục mới hơn, cập nhật plugin như mọi plugin khác — địa chỉ đã lưu không bị thay đổi.

Người bảo trì dựng lại file danh mục bằng lệnh có sẵn trong plugin:

1. Mở [danhmuchanhchinh.nso.gov.vn](https://danhmuchanhchinh.nso.gov.vn/), chọn **Cấp: Xã**, chọn **Đến ngày**, bấm
   **Thực Hiện** rồi **Xuất Excel**.
2. Từ thư mục gốc website: `php app/GP247/Plugins/AddressVN/tools/build-catalog.php <file.xls> <YYYY-MM-DD>`.
   Lệnh dừng mà không ghi gì nếu file có dòng bất thường (sai tiêu đề, trùng mã, một tỉnh hai tên).

## Nguồn dữ liệu

| Nguồn | Phiên bản / ngày | Plugin dùng để |
| --- | --- | --- |
| [Danh mục đơn vị hành chính — Cục Thống kê](https://danhmuchanhchinh.nso.gov.vn/), cấp Xã, xuất Excel | Hiệu lực đến ngày 24/11/2026 · 34 tỉnh, 3.321 đơn vị cấp xã (709 phường, 2.599 xã, 13 đặc khu) | Mã và tên chính thức của tỉnh/thành phố và phường/xã/đặc khu (`Data/vn-admin-units.json`) |

## Lịch sử phiên bản

- **1.0.0** — Phát hành đầu tiên: chọn tỉnh → phường/xã ở storefront và màn quản trị, lưu tên chính thức kèm mã, khớp tên
  gõ tay, danh mục ngày 24/11/2026.
